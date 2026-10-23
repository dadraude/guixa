<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Membership\Application\DeactivateAccount;
use Guixa\Membership\Application\ReactivateAccount;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

class AccountDeactivationController extends Controller
{
    public function store(string $account, Actor $actor, DeactivateAccount $deactivateAccount): RedirectResponse
    {
        $deactivateAccount($actor, $account);

        return back();
    }

    public function destroy(string $account, Actor $actor, ReactivateAccount $reactivateAccount): RedirectResponse
    {
        $reactivateAccount($actor, $account);

        return back();
    }
}
