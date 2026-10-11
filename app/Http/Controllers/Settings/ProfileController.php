<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\RequestEmailChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(#[CurrentUser] User $user): Response
    {
        // The phone lives on the member profile, so users without one (e.g. an
        // administrator) get no phone field at all.
        $memberProps = $user->member
            ? ['hasMember' => true, 'phone' => $user->member->phone]
            : ['hasMember' => false];

        return Inertia::render('settings/Profile', [
            ...$memberProps,
            // An expired link can no longer confirm anything, so it is not shown.
            'pendingEmail' => $user->hasPendingEmailChange() ? $user->pending_email : null,
        ]);
    }

    /**
     * Update the signed-in user's own details. An email change is only
     * requested here; it applies when the link sent to the new address is opened.
     */
    public function update(ProfileUpdateRequest $request, #[CurrentUser] User $user, RequestEmailChange $requestEmailChange): RedirectResponse
    {
        $validated = $request->validated();

        $user->update(['name' => $validated['name']]);
        if ($user->member) {
            $user->member->update(['phone' => $validated['phone'] ?? null]);
        }

        $email = $request->safe()->string('email')->toString();
        if ($email !== $user->email) {
            $requestEmailChange->handle($user, $email);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }
}
