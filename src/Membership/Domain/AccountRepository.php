<?php

namespace Guixa\Membership\Domain;

use Guixa\Membership\Domain\ValueObject\Email;

interface AccountRepository
{
    public function nextId(): string;

    public function save(Account $account): void;

    public function get(string $id): ?Account;

    public function emailIsTaken(Email $email): bool;

    /** @return list<Account> every account, by name */
    public function list(): array;

    public function producerHasAccount(string $producerId): bool;

    /** @return list<Account> accounts that belong to a producer */
    public function producerAccounts(): array;

    public function hasManager(): bool;

    /** @return list<Account> */
    public function membersOf(string $householdId): array;

    /** Whether an account other than this one holds the manager role and is not deactivated. Call it after `lockManagers()`. */
    public function hasOtherActiveManager(string $accountId): bool;

    /**
     * Locks every account that holds the manager role until the transaction ends, so two calls that check
     * "is this the last manager" run one after the other (R36a, R36b). Call it before `hasOtherActiveManager()`.
     */
    public function lockManagers(): void;
}
