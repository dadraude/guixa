<?php

namespace App\Http\Controllers\Settings;

use App\Actions\RequestEmailChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Profile', [
            'phone' => $user->member?->phone,
            'pendingEmail' => $user->pending_email,
        ]);
    }

    /**
     * Update the signed-in user's own details. An email change is only
     * requested here; it applies when the link sent to the new address is opened.
     */
    public function update(ProfileUpdateRequest $request, RequestEmailChange $requestEmailChange): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->update(['name' => $validated['name']]);
        $user->member?->update(['phone' => $validated['phone'] ?? null]);

        if ($validated['email'] !== $user->email) {
            $requestEmailChange->handle($user, $validated['email']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }
}
