<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountListing;
use Guixa\Membership\Domain\AccountRepository;
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

        return array_map(
            fn (Role $role) => new StaffRoleGroupData($role, array_map(
                fn (AccountListing $listing) => new StaffRoleHolderData(
                    $listing->accountId,
                    $listing->name,
                    $listing->email,
                    $listing->status($now),
                    $listing->accountId === $actor->accountId,
                    // Removal arrives with T02; until then no row offers it.
                    false,
                    null,
                ),
                $holders[$role->value] ?? [],
            )),
            Role::staff(),
        );
    }
}
