<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ConfirmEmailChange;
use App\Http\Controllers\Controller;
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
        if (! $confirmEmailChange->handle($token)) {
            return to_route('email-change.show', ['token' => $token]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email address updated.')]);

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
