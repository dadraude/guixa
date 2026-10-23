<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Membership\Application\GrantManagerRole;
use Guixa\Membership\Application\RemoveManagerRole;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

class ManagerRoleController extends Controller
{
    public function update(string $account, Actor $actor, GrantManagerRole $grantManagerRole): RedirectResponse
    {
        $grantManagerRole($actor, $account);

        return back();
    }

    public function destroy(string $account, Actor $actor, RemoveManagerRole $removeManagerRole): RedirectResponse
    {
        $removeManagerRole($actor, $account);

        return back();
    }
}
