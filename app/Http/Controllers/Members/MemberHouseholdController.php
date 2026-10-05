<?php

namespace App\Http\Controllers\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MoveMemberRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MemberHouseholdController extends Controller
{
    /**
     * Only the household changes: orders and balances belong to the household
     * they were made in and stay there.
     */
    public function update(MoveMemberRequest $request, Member $member): RedirectResponse
    {
        $member->update(['household_id' => $request->validated('household_id')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member moved.')]);

        return to_route('members.index');
    }
}
