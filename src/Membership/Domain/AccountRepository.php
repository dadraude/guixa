<?php

namespace Guixa\Membership\Domain;

use Guixa\Membership\Domain\ValueObject\Email;

interface AccountRepository
{
    public function nextId(): string;

    public function save(Account $account): void;

    public function get(string $id): ?Account;

    public function emailIsTaken(Email $email): bool;

    public function producerHasAccount(string $producerId): bool;

    /** @return list<Account> accounts that belong to a producer */
    public function producerAccounts(): array;

    public function hasManager(): bool;

    /** @return list<Account> */
    public function membersOf(string $householdId): array;
}
