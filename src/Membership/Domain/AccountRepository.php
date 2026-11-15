<?php

namespace Guixa\Membership\Domain;

use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Domain\Role;

interface AccountRepository
{
    public function nextId(): string;

    /** Existing-account detail saves leave the stored household link unchanged. */
    public function save(Account $account): void;

    /** Persist only the domain-approved household link change under the membership lock. */
    public function saveHouseholdLink(Account $account): void;

    public function get(string $id): ?Account;

    /**
     * @param  list<string>  $ids
     * @return array<string, string> name by account id in one read, deactivated accounts included; unknown ids are left out
     */
    public function namesByIds(array $ids): array;

    public function emailIsTaken(Email $email): bool;

    /** @return list<Account> every account, by name */
    public function list(): array;

    /** The one account of the producer, if it has one. */
    public function ofProducer(string $producerId): ?Account;

    public function producerHasAccount(string $producerId): bool;

    /** @return list<Account> accounts that belong to a producer */
    public function producerAccounts(): array;

    public function hasManager(): bool;

    /** @return list<Account> */
    public function membersOf(string $householdId): array;

    /** Whether an account other than this one holds the manager role, accepted its invitation and is not deactivated. Call it after `lockManagers()`. */
    public function hasOtherActiveManager(string $accountId): bool;

    /**
     * Locks every account that holds the manager role until the transaction ends, so two calls that check
     * "is this the last manager" run one after the other (R36a, R36b). Call it before `hasOtherActiveManager()`.
     */
    public function lockManagers(): void;

    /**
     * @return array<string, list<AccountListing>> the holders of every staff role by role value, deactivated accounts
     *                                             included, each list by name then email (R9)
     */
    public function listStaffRoleHolders(): array;

    /**
     * Accounts whose name or email contains the query, case-insensitive, that are not deactivated and do not hold
     * the role, by name then email. An empty query finds nothing (R10).
     *
     * @return list<AccountListing>
     */
    public function searchStaffRoleCandidates(Role $role, string $query, int $limit): array;
}
