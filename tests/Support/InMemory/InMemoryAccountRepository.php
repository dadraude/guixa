<?php

namespace Tests\Support\InMemory;

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountListing;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Domain\Role;

/** @extends InMemoryRepository<Account> */
final class InMemoryAccountRepository extends InMemoryRepository implements AccountRepository
{
    /** Pass the invitations the listings read their status from. */
    public function __construct(private readonly ?InMemoryInvitationRepository $invitations = null) {}

    /** @param Account $item */
    public function save(object $item): void
    {
        $stored = $this->find($item->id);

        if ($stored !== null) {
            $item = Account::restore(
                $item->id, $item->name, $item->email, $item->roles, $stored->householdId,
                $item->producerId, $item->activatedAt, $item->deactivatedAt,
            );
        }

        parent::save($item);
    }

    public function namesByIds(array $ids): array
    {
        $names = [];

        foreach (array_unique($ids) as $id) {
            $account = $this->find($id);

            if ($account !== null) {
                $names[$id] = $account->name;
            }
        }

        return $names;
    }

    public function saveHouseholdLink(Account $account): void
    {
        $stored = $this->find($account->id);

        if ($stored === null) {
            return;
        }

        parent::save(Account::restore(
            $stored->id, $stored->name, $stored->email, $stored->roles, $account->householdId,
            $stored->producerId, $stored->activatedAt, $stored->deactivatedAt,
        ));
    }

    public function get(string $id): ?Account
    {
        return $this->find($id);
    }

    public function emailIsTaken(Email $email): bool
    {
        foreach ($this->all() as $account) {
            if ($account->email->value === $email->value) {
                return true;
            }
        }

        return false;
    }

    public function list(): array
    {
        $accounts = $this->all();
        usort($accounts, fn (Account $a, Account $b) => [$a->name, $a->id] <=> [$b->name, $b->id]);

        return $accounts;
    }

    public function ofProducer(string $producerId): ?Account
    {
        foreach ($this->all() as $account) {
            if ($account->producerId === $producerId) {
                return $account;
            }
        }

        return null;
    }

    public function producerHasAccount(string $producerId): bool
    {
        foreach ($this->all() as $account) {
            if ($account->producerId === $producerId) {
                return true;
            }
        }

        return false;
    }

    public function producerAccounts(): array
    {
        return array_values(array_filter($this->all(), fn (Account $account) => $account->producerId !== null));
    }

    public function hasManager(): bool
    {
        foreach ($this->all() as $account) {
            if (in_array(Role::Manager, $account->roles, true)) {
                return true;
            }
        }

        return false;
    }

    public function membersOf(string $householdId): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (Account $account) => $account->householdId === $householdId,
        ));
    }

    public function hasOtherActiveManager(string $accountId): bool
    {
        foreach ($this->all() as $manager) {
            if ($manager->id !== $accountId && $manager->isActiveManager()) {
                return true;
            }
        }

        return false;
    }

    public function listStaffRoleHolders(): array
    {
        $holders = array_fill_keys(array_map(fn (Role $role) => $role->value, Role::staff()), []);

        foreach ($this->sortedByNameThenEmail() as $account) {
            foreach ($account->roles as $role) {
                if ($role->isStaff()) {
                    $holders[$role->value][] = $this->listing($account);
                }
            }
        }

        return $holders;
    }

    public function searchStaffRoleCandidates(Role $role, string $query, int $limit): array
    {
        $query = mb_strtolower(trim($query));

        if ($query === '') {
            return [];
        }

        $matches = array_filter($this->sortedByNameThenEmail(), fn (Account $account) => $account->isActive()
            && ! in_array($role, $account->roles, true)
            && (str_contains(mb_strtolower($account->name), $query) || str_contains($account->email->value, $query)));

        return array_map($this->listing(...), array_slice(array_values($matches), 0, $limit));
    }

    /** @return list<Account> */
    private function sortedByNameThenEmail(): array
    {
        $accounts = $this->all();
        usort($accounts, fn (Account $a, Account $b) => [mb_strtolower($a->name), $a->email->value, $a->id] <=> [mb_strtolower($b->name), $b->email->value, $b->id]);

        return $accounts;
    }

    private function listing(Account $account): AccountListing
    {
        return new AccountListing(
            $account->id,
            $account->name,
            $account->email->value,
            $account->activatedAt,
            $account->deactivatedAt,
            $this->invitations?->forAccount($account->id)?->sentAt,
        );
    }

    /** Nothing runs concurrently in memory. */
    public function lockManagers(): void {}

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
