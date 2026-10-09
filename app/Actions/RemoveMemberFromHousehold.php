<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Leaves a member, active or inactive, without a household. Commissions stay:
 * roles work with or without a household. The state is read under a lock so a
 * concurrent move is respected.
 */
class RemoveMemberFromHousehold
{
    /**
     * @throws ValidationException
     */
    public function handle(Member $member): void
    {
        DB::transaction(function () use ($member): void {
            $current = Member::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();

            if ($current->household_id === null) {
                throw ValidationException::withMessages(['member' => __('This member has no household.')]);
            }

            // Update the locked row, never the possibly stale route-bound model.
            $current->update(['household_id' => null]);
        });
    }
}
