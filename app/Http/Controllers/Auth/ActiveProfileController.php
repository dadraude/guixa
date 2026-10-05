<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\ActiveProfile;
use App\Auth\Profile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SwitchProfileRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ActiveProfileController extends Controller
{
    public function __construct(private readonly ActiveProfile $activeProfile) {}

    /**
     * Ask a person with both profiles which one to act as. Everyone else has
     * nothing to choose.
     */
    public function edit(#[CurrentUser] User $user): Response|RedirectResponse
    {
        $profiles = $this->activeProfile->available($user);

        if (count($profiles) < 2) {
            return to_route('home');
        }

        return Inertia::render('auth/ChooseProfile', [
            'profiles' => array_map(fn (Profile $profile) => $profile->value, $profiles),
        ]);
    }

    /**
     * Choose or switch profile. The home depends on the profile, so go there
     * instead of back to a page the new profile may not be allowed to see,
     * unless sign-in left a page the person asked for before choosing.
     */
    public function update(SwitchProfileRequest $request): RedirectResponse
    {
        $this->activeProfile->choose($request->profile());

        return redirect()->intended(route('home'));
    }
}
