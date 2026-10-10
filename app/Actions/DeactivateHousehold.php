<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\MemberRoles;
use App\Auth\MembershipRoleProtection;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use App\Support\HouseholdStatusChange;
use Illuminate\Support\Facades\DB;

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
        private readonly MemberRoles $memberRoles,
    ) {}

    /**
     * @return HouseholdStatusChange The members left active are named in it.
     */
    public function handle(Household $household, User $actor, bool $withMembers): HouseholdStatusChange
    {
        return DB::transaction(function () use ($household, $actor, $withMembers): HouseholdStatusChange {
            // Locks the household row so concurrent household changes are serialized.
            $locked = Household::query()->whereKey($household->getKey())->lockForUpdate()->firstOrFail();

            // A stale second request must not deactivate members reactivated since.
            if (! $locked->is_active) {
                return HouseholdStatusChange::alreadyInThatState();
            }

            $locked->forceFill(['is_active' => false])->save();

            if (! $withMembers) {
                return HouseholdStatusChange::changed();
            }

            // Locked after the household and selected by their current household, so one moved
            // elsewhere meanwhile is never touched; a fresh query, as a preloaded relation could be stale.
            $members = Member::query()
                ->where('household_id', $locked->getKey())
                ->where('is_active', true)
                ->with('user.administrator')
                ->lockForUpdate()
                ->get();
            $this->memberRoles->preload($members);

            $skippedNames = [];

            foreach ($members as $member) {
                if (! $this->protection->mayDeactivateAlongWithHousehold($actor, $member)) {
                    $skippedNames[] = $member->user->name;

                    continue;
                }

                $this->deactivateMember->handle($member);
            }

            return HouseholdStatusChange::changed($skippedNames);
        });
    }
}
