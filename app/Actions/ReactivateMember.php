<?php

namespace App\Actions;

use App\Models\Household;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A member only comes back into a household that is active; commissions are
 * not restored, they are assigned again by hand.
 *
 * The household row is locked while the check and the update run, and
 * DeactivateHousehold locks the same row, so a member can never end up active
 * in a household that was deactivated in between.
 */
class ReactivateMember
{
    public function handle(Member $member): void
    {
        DB::transaction(function () use ($member) {
            // The loaded member may be stale (moved meanwhile): read its household from the DB.
            // No lock on the member here, so the household lock is always taken first.
            $householdId = Member::query()->whereKey($member->getKey())->value('household_id');

            // Read the locked row, never the possibly stale loaded relation.
            $household = Household::query()->whereKey($householdId)->lockForUpdate()->firstOrFail();

            if (! $household->is_active) {
                throw ValidationException::withMessages([
                    'member' => __('Reactivate the household first.'),
                ]);
            }

            $member->update(['is_active' => true]);
        });
    }
}
