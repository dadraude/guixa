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

        $existingUser = $invitation->existingUser();

        return Inertia::render('invitations/Accept', [
            'token' => $token,
            'email' => $invitation->email,
            'existingUser' => $existingUser !== null,
            'name' => $existingUser === null ? $invitation->name : $existingUser->name,
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

        if ($user->wasRecentlyCreated) {
            Auth::login($user, remember: true);
            $request->session()->regenerate();

            return to_route('home');
        }

        // The link proves the email but is valid for days, so it must not
        // sign in an existing account that already has its own way in.
        if (Auth::id() === $user->id) {
            return to_route('home');
        }

        return to_route('login')->with('status', __('Invitation accepted. Log in to join your household.'));
    }
}
