<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Membership\Application\GrantFinanceRole;
use Guixa\Membership\Application\RemoveFinanceRole;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

final class FinanceRoleController extends Controller
{
    public function update(string $account, Actor $actor, GrantFinanceRole $grant): RedirectResponse
    {
        $grant($actor, $account);

        return back();
    }

    public function destroy(string $account, Actor $actor, RemoveFinanceRole $remove): RedirectResponse
    {
        $remove($actor, $account);

        return back();
    }
}
