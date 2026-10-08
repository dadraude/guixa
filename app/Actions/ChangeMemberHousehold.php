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

            $member->update(['household_id' => $household->id]);
        });
    }
}
