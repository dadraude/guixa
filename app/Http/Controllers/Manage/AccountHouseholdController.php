<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Membership\Application\MoveMember;
use Guixa\Membership\Application\UnlinkMember;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountHouseholdController extends Controller
{
    public function update(string $account, Request $request, Actor $actor, MoveMember $moveMember): RedirectResponse
    {
        $data = $request->validate(['household_id' => ['required', 'uuid']]);

        $moveMember($actor, $account, $data['household_id']);

        return back();
    }

    public function destroy(string $account, Actor $actor, UnlinkMember $unlinkMember): RedirectResponse
    {
        $unlinkMember($actor, $account);

        return back();
    }
}
