<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Household;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assigns a member's first household or moves them to another one: it is the
 * same action. The target household is locked while it is checked and the
 * member is updated, and DeactivateHousehold locks the same row, so a member
 * never lands in a household deactivated in between. Two simultaneous changes
 * on the same member each validate under their own lock and the last one wins.
 *
 * Inactive members can be moved too and stay inactive until reactivated.
 * Orders and balances (later specs) stay with the household they belong to.
 */
class ChangeMemberHousehold
{
    /**
     * @throws ValidationException
     */
    public function handle(Member $member, string $householdId): void
    {
        DB::transaction(function () use ($member, $householdId): void {
            $household = Household::query()->whereKey($householdId)->lockForUpdate()->first();

            if ($household?->is_active !== true) {
                throw ValidationException::withMessages(['household_id' => __('Choose an active household.')]);
            }

            // The household lock is taken first (as in ReactivateMember), then a fresh copy of
            // the member: updating the possibly stale route-bound model would skip the UPDATE
            // when its loaded value equals the new one but another change was committed meanwhile.
            $current = Member::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();
            $current->update(['household_id' => $household->id]);
            $member->setRawAttributes($current->getAttributes(), true);
        });
    }
}
