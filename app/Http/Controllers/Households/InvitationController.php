<?php

namespace App\Http\Controllers\Households;

use App\Actions\InviteToHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Households\InviteToHouseholdRequest;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;

class InvitationController extends Controller
{
    public function store(InviteToHouseholdRequest $request, Household $household, InviteToHousehold $inviteToHousehold): RedirectResponse
    {
        $inviteToHousehold->handle($household, $request->validated('email'), $request->validated('name'));

        return to_route('households.index')->with('status', __('Invitation sent.'));
    }
}
