<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ConfirmEmailChange;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EmailChangeController extends Controller
{
    /**
     * The token in the link is the proof, so it works from any browser.
     */
    public function confirm(ConfirmEmailChange $confirmEmailChange, string $token): RedirectResponse
    {
        $changed = $confirmEmailChange->handle($token);

        Inertia::flash('toast', [
            'type' => $changed ? 'success' : 'error',
            'message' => $changed
                ? __('Email address updated.')
                : __('This link has expired or has already been used.'),
        ]);

        return to_route('profile.edit');
    }
}
