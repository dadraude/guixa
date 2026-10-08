<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Leaves an active member without a household. Commissions stay: roles work
 * with or without a household. Inactive members cannot be removed (change
 * their household instead). The state is read under a lock so a deactivation
 * made in between is respected.
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

            if (! $current->is_active) {
                throw ValidationException::withMessages(['member' => __('Only active members can be removed from their household.')]);
            }

            $member->update(['household_id' => null]);
        });
    }
}
