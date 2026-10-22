<?php

namespace Tests\Support\InMemory;

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\ValueObject\Email;

/** @extends InMemoryRepository<Account> */
final class InMemoryAccountRepository extends InMemoryRepository implements AccountRepository
{
    /** @param Account $item */
    public function save(object $item): void
    {
        parent::save($item);
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

    public function membersOf(string $householdId): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (Account $account) => $account->householdId === $householdId,
        ));
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
