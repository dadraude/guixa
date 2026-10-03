<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The mandatory first step for a user who signed in without a password yet.
 */
class SetPasswordController extends Controller
{
    use PasswordValidationRules;

    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->password !== null) {
            return to_route('home');
        }

        return Inertia::render('auth/SetPassword', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || $user->password !== null) {
            return to_route('home');
        }

        $validated = $request->validate(['password' => $this->passwordRules()]);

        $user->update(['password' => $validated['password']]);

        return to_route('home');
    }
}
