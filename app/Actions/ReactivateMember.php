<?php

namespace App\Actions;

use App\Models\Member;
use Illuminate\Validation\ValidationException;

/**
 * A member only comes back into a household that is active; commissions are
 * not restored, they are assigned again by hand.
 */
class ReactivateMember
{
    public function handle(Member $member): void
    {
        if (! $member->household->is_active) {
            throw ValidationException::withMessages([
                'member' => __('Reactivate the household first.'),
            ]);
        }

        $member->update(['is_active' => true]);
    }
}
