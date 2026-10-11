<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\MembershipRoleProtection;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use App\Support\HouseholdStatusChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A household leaves the cooperative; its number, data and history are kept.
 * Alone, it deactivates nobody: its active members become members without a
 * household (ADR 0011). With its members, the active ones the actor may not
 * deactivate on their own are skipped and named.
 */
class DeactivateHousehold
{
    public function __construct(
        private readonly DeactivateMember $deactivateMember,
        private readonly MembershipRoleProtection $protection,
        private readonly ChangeHouseholdMembersStatus $householdMembers,
    ) {}

    /**
     * @return HouseholdStatusChange The members left active are named in it.
     */
    public function handle(Household $household, User $actor, ?bool $withMembers = null): HouseholdStatusChange
    {
        return DB::transaction(function () use ($household, $actor, $withMembers): HouseholdStatusChange {
            // Locks the household row so concurrent household changes are serialized.
            $locked = Household::query()->whereKey($household->getKey())->lockForUpdate()->firstOrFail();

            // A stale second request must not deactivate members reactivated since.
            if (! $locked->is_active) {
                return HouseholdStatusChange::alreadyInThatState();
            }

            $mayChange = fn (Member $member): bool => $this->protection->mayChangeAlongWithHousehold($actor, $member, householdIsActive: true);

            // No choice was made because the client saw nobody who would change: confirm it now, with the
            // members locked, and roll back so the client asks again if somebody would (DRA-94).
            if ($withMembers === null && $this->householdMembers->anyoneWouldChange($locked, currentlyActive: true, mayChange: $mayChange)) {
                throw ValidationException::withMessages(['with_members' => __('The members of this household changed. Review them and choose again.')]);
            }

            $locked->forceFill(['is_active' => false])->save();

            if ($withMembers !== true) {
                return HouseholdStatusChange::changed();
            }

            $skippedNames = $this->householdMembers->handle(
                $locked,
                currentlyActive: true,
                mayChange: $mayChange,
                change: fn (Member $member) => $this->deactivateMember->handle($member),
            );

            return HouseholdStatusChange::changed($skippedNames);
        });
    }
}
