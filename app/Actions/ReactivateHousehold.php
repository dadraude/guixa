<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\MembershipRoleProtection;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use App\Support\HouseholdStatusChange;
use Illuminate\Support\Facades\DB;

/**
 * The household comes back with the same number and data. Its members stay
 * inactive unless the actor chooses to reactivate them too: then every inactive
 * member in the household at that moment is, however they became inactive,
 * except the ones the actor may not reactivate on their own, who are named.
 */
class ReactivateHousehold
{
    public function __construct(
        private readonly ReactivateMember $reactivateMember,
        private readonly MembershipRoleProtection $protection,
        private readonly ChangeHouseholdMembersStatus $householdMembers,
    ) {}

    /**
     * @return HouseholdStatusChange The members left inactive are named in it.
     */
    public function handle(Household $household, User $actor, bool $withMembers): HouseholdStatusChange
    {
        return DB::transaction(function () use ($household, $actor, $withMembers): HouseholdStatusChange {
            $locked = Household::query()->whereKey($household->getKey())->lockForUpdate()->firstOrFail();

            // A stale second request must not reactivate members deactivated since.
            if ($locked->is_active) {
                return HouseholdStatusChange::alreadyInThatState();
            }

            $locked->forceFill(['is_active' => true])->save();

            if (! $withMembers) {
                return HouseholdStatusChange::changed();
            }

            $skippedNames = $this->householdMembers->handle(
                $locked,
                currentlyActive: false,
                mayChange: fn (Member $member): bool => $this->protection->mayReactivateMember($actor, $member),
                change: fn (Member $member) => $this->reactivateMember->handle($member),
            );

            return HouseholdStatusChange::changed($skippedNames);
        });
    }
}
