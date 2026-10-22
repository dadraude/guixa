<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Membership\Application\ChangeInvitationEmail;
use Guixa\Membership\Application\ResendInvitation;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function resend(string $account, Actor $actor, ResendInvitation $resendInvitation): RedirectResponse
    {
        $resendInvitation($actor, $account);

        return back();
    }

    public function changeEmail(string $account, Request $request, Actor $actor, ChangeInvitationEmail $changeInvitationEmail): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $changeInvitationEmail($actor, $account, $data['email']);

        return back();
    }
}
