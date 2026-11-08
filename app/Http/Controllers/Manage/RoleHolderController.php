<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\GrantStaffRoleRequest;
use Guixa\Membership\Application\GrantStaffRole;
use Guixa\Membership\Application\RemoveStaffRole;
use Guixa\Membership\Domain\Error\NotAStaffRole;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RoleHolderController extends Controller
{
    public function store(string $staffRole, GrantStaffRoleRequest $request, Actor $actor, GrantStaffRole $grant): RedirectResponse
    {
        try {
            $grant($actor, $request->string('account_id')->toString(), $staffRole);
        } catch (NotAStaffRole) {
            // Producer, Member and unknown names are not roles of this screen.
            throw new NotFoundHttpException;
        }

        return back();
    }

    public function destroy(string $staffRole, string $account, Actor $actor, RemoveStaffRole $remove): RedirectResponse
    {
        try {
            $remove($actor, $account, $staffRole);
        } catch (NotAStaffRole) {
            throw new NotFoundHttpException;
        }

        // The manager who removed their own Manager role can no longer open this screen.
        if ($staffRole === Role::Manager->value && $account === $actor->accountId) {
            return redirect()->route('dashboard');
        }

        return back();
    }
}
