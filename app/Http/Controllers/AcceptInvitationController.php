<?php

namespace App\Http\Controllers;

use Guixa\Membership\Application\AcceptInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function show(string $token): Response
    {
        return Inertia::render('auth/AcceptInvitation', [
            'token' => $token,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(string $token, Request $request, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'confirmed', Password::defaults()]]);

        $acceptInvitation($token, $data['password']);

        return to_route('login')->with('status', __('Your password is set. You can log in now.'));
    }
}
