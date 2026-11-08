<?php

namespace Guixa\Membership\Application;

use DateTimeImmutable;
use Guixa\Membership\Domain\AccountListing;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\AccountStatus;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\Gate;

final class ListStaffRoleHolders
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly Clock $clock,
    ) {}

    /** @return list<StaffRoleGroupData> one group per staff role, in the screen's order (R9) */
    public function __invoke(Actor $actor): array
    {
        Gate::forUser($actor)->authorize('manage-roles');

        $holders = $this->accounts->listStaffRoleHolders();
        $now = $this->clock->now();

        $activeManagers = count(array_filter(
            $holders[Role::Manager->value] ?? [],
            fn (AccountListing $listing) => $listing->status($now) === AccountStatus::Active,
        ));

        return array_map(
            fn (Role $role) => new StaffRoleGroupData($role, array_map(
                fn (AccountListing $listing) => $this->holderData($actor, $role, $listing, $now, $activeManagers),
                $holders[$role->value] ?? [],
            )),
            Role::staff(),
        );
    }

    private function holderData(Actor $actor, Role $role, AccountListing $listing, DateTimeImmutable $now, int $activeManagers): StaffRoleHolderData
    {
        $status = $listing->status($now);
        $isLastActiveManager = $role === Role::Manager && $status === AccountStatus::Active && $activeManagers === 1;

        return new StaffRoleHolderData(
            $listing->accountId,
            $listing->name,
            $listing->email,
            $status,
            $listing->accountId === $actor->accountId,
            ! $isLastActiveManager,
            $isLastActiveManager ? __('It is the last active manager: another account must hold the Manager role and have accepted its invitation first.') : null,
        );
    }
}
