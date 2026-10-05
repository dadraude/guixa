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

        // The phone lives on the member profile, so users without one (e.g. an
        // administrator) get no phone field at all.
        $memberProps = $user->member
            ? ['hasMember' => true, 'phone' => $user->member->phone]
            : ['hasMember' => false];

        return Inertia::render('settings/Profile', [
            ...$memberProps,
            // An expired link can no longer confirm anything, so it is not shown.
            'pendingEmail' => $user->pending_email_expires_at?->isFuture() ? $user->pending_email : null,
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
        if ($user->member) {
            $user->member->update(['phone' => $validated['phone'] ?? null]);
        }

        if ($validated['email'] !== $user->email) {
            $requestEmailChange->handle($user, $validated['email']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }
}
