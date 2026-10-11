<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\ActiveProfile;
use App\Auth\IntendedUrlAccess;
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
    public function __construct(
        private readonly ActiveProfile $activeProfile,
        private readonly IntendedUrlAccess $intendedUrlAccess,
    ) {}

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
     * unless sign-in left a page the person asked for before choosing and the
     * chosen profile may see it; otherwise that page is dropped and it is home.
     */
    public function update(SwitchProfileRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $this->activeProfile->choose($request->profile());

        $intended = $request->session()->get('url.intended');

        if (is_string($intended) && ! $this->intendedUrlAccess->allows($user, $intended)) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route('home'));
    }
}
