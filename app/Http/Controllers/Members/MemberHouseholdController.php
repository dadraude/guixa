<?php

declare(strict_types=1);

namespace App\Http\Controllers\Members;

use App\Actions\ChangeMemberHousehold;
use App\Actions\RemoveMemberFromHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MoveMemberRequest;
use App\Models\Member;
use App\Support\MembersListRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MemberHouseholdController extends Controller
{
    /**
     * Only the household changes: orders and balances belong to the household
     * they were made in and stay there.
     */
    public function update(MoveMemberRequest $request, Member $member, ChangeMemberHousehold $changeMemberHousehold): RedirectResponse
    {
        $changeMemberHousehold->handle($member, $request->safe()->string('household_id')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Household changed.')]);

        return MembersListRedirect::back($request);
    }

    public function destroy(Request $request, Member $member, RemoveMemberFromHousehold $removeMemberFromHousehold): RedirectResponse
    {
        $removeMemberFromHousehold->handle($member);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed from the household.')]);

        return MembersListRedirect::back($request);
    }
}
