<?php

namespace App\Http\Controllers\Invitations;

use App\Actions\AcceptInvitation;
use App\Actions\InvitationUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitations\AcceptInvitationRequest;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    /**
     * Show the form without using the invitation up, so that mail scanners and
     * link previews that open it do not spend it.
     */
    public function show(string $token): Response
    {
        $invitation = Invitation::query()->withToken($token)->usable()->first();

        if ($invitation === null) {
            return Inertia::render('invitations/Unavailable');
        }

        return Inertia::render('invitations/Accept', [
            'token' => $token,
            'email' => $invitation->email,
            'name' => $invitation->name,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        try {
            $user = $acceptInvitation->handle($token, $request->validated('name'), $request->validated('password'));
        } catch (InvitationUnavailable) {
            return to_route('invitations.show', ['token' => $token]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return to_route('home');
    }
}
