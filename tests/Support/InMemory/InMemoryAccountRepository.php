<?php

namespace Tests\Support\InMemory;

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Domain\Role;

/** @extends InMemoryRepository<Account> */
final class InMemoryAccountRepository extends InMemoryRepository implements AccountRepository
{
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

    /** Nothing runs concurrently in memory. */
    public function lockManagers(): void {}

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
