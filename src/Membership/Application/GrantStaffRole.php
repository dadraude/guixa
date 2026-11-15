<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Membership\Domain\Error\NotAStaffRole;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Membership\Domain\RoleChange;
use Guixa\Membership\Domain\RoleChangeLog;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\Gate;

final class GrantStaffRole
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly HouseholdRepository $households,
        private readonly RoleChangeLog $changes,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    /** @param string $role the name of a staff role: manager, finance, catalogue or receiver */
    public function __invoke(Actor $actor, string $accountId, string $role): void
    {
        Gate::forUser($actor)->authorize('manage-roles');

        $staffRole = Role::tryFrom($role);

        if ($staffRole === null || ! $staffRole->isStaff()) {
            throw NotAStaffRole::named($role);
        }

        $this->transactions->run(function () use ($actor, $accountId, $staffRole): void {
            $this->households->lockMembershipChanges();
            if ($staffRole === Role::Manager) {
                $this->accounts->lockManagers();
            }

            // Re-read under the lock: a stale screen then meets the current holders (R13).
            $account = $this->accounts->get($accountId) ?? throw AccountNotFound::withId($accountId);
            $account->grantRole($staffRole);
            $this->accounts->save($account);
            $this->changes->record($accountId, $staffRole, RoleChange::Granted, $actor->accountId, $this->clock->now());
        });
    }
}
