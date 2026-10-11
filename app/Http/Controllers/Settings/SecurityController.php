<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\ChangePassword;
use App\Auth\MagicLinkSignIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Security', [
            'requiresCurrentPassword' => ! MagicLinkSignIn::isRecent($request->session()),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request, #[CurrentUser] User $user, ChangePassword $changePassword): RedirectResponse
    {
        $changePassword->handle($user, $request->safe()->string('password')->toString(), $request->session());

        MagicLinkSignIn::clear($request->session());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
