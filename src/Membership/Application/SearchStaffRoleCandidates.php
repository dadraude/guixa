<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountListing;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\Gate;

final class SearchStaffRoleCandidates
{
    private const LIMIT = 20;

    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly Clock $clock,
    ) {}

    /**
     * @param  string  $role  the name of a staff role; anything else finds nothing, as no account can be granted it here
     * @return list<StaffRoleCandidateData>
     */
    public function __invoke(Actor $actor, string $role, string $query): array
    {
        Gate::forUser($actor)->authorize('manage-roles');

        $role = Role::tryFrom($role);

        if ($role === null || ! $role->isStaff()) {
            return [];
        }

        $now = $this->clock->now();

        return array_map(
            fn (AccountListing $listing) => new StaffRoleCandidateData(
                $listing->accountId,
                $listing->name,
                $listing->email,
                $listing->status($now),
            ),
            $this->accounts->searchStaffRoleCandidates($role, $query, self::LIMIT),
        );
    }
}
