<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\ConfirmEmailChange;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmailChangeController extends Controller
{
    /**
     * Ask for one click before applying the change, so that mail scanners and
     * link previews that open the link do not apply it. Nothing changes here.
     */
    public function show(ConfirmEmailChange $confirmEmailChange, string $token): Response|RedirectResponse
    {
        if (! $confirmEmailChange->isPending($token)) {
            return $this->rejectLink();
        }

        return Inertia::render('auth/EmailChangeConfirm', ['token' => $token]);
    }

    /**
     * The token in the link is the proof, so it works from any browser.
     */
    public function confirm(ConfirmEmailChange $confirmEmailChange, string $token): RedirectResponse
    {
        if (! $confirmEmailChange->handle($token)) {
            return $this->rejectLink();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email address updated.')]);

        return to_route('profile.edit');
    }

    private function rejectLink(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __('This link has expired or has already been used.')]);

        return to_route('profile.edit');
    }
}
