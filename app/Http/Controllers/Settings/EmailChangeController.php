<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\CancelEmailChange;
use App\Actions\ConfirmEmailChange;
use App\Actions\EmailChangeOutcome;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailChangeController extends Controller
{
    /**
     * Ask for one click before applying the change, so that mail scanners and
     * link previews that open the link do not apply it. Nothing changes here.
     */
    public function show(Request $request, ConfirmEmailChange $confirmEmailChange, string $token): Response
    {
        if (! $confirmEmailChange->isPending($token)) {
            return $this->rejectLink($request);
        }

        return Inertia::render('auth/EmailChangeConfirm', ['token' => $token]);
    }

    /**
     * The token in the link is the proof, so it works from any browser.
     */
    public function confirm(ConfirmEmailChange $confirmEmailChange, string $token): RedirectResponse
    {
        $outcome = $confirmEmailChange->handle($token);

        if ($outcome === EmailChangeOutcome::LinkUnusable) {
            return to_route('email-change.show', ['token' => $token]);
        }

        if ($outcome === EmailChangeOutcome::EmailTaken) {
            return to_route('email-change.taken');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email address updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Public, like the unavailable page: the link works from any browser, and
     * without a session the profile would redirect to the login and lose the message.
     */
    public function taken(Request $request): Response
    {
        return Inertia::render('auth/EmailChangeTaken', ['signedIn' => $request->user() !== null]);
    }

    public function cancel(#[CurrentUser] User $user, CancelEmailChange $cancelEmailChange): RedirectResponse
    {
        $cancelEmailChange->handle($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email change cancelled.')]);

        return to_route('profile.edit');
    }

    /**
     * Rendered on a public page, not as a toast on the profile: without a session
     * the profile redirects to the login and the explanation would be lost.
     */
    private function rejectLink(Request $request): Response
    {
        return Inertia::render('auth/EmailChangeUnavailable', ['signedIn' => $request->user() !== null]);
    }
}
