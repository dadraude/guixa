<?php

namespace App\Http\Controllers\Auth;

use App\Auth\ActiveProfile;
use App\Auth\Profile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SwitchProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActiveProfileController extends Controller
{
    public function __construct(private readonly ActiveProfile $activeProfile) {}

    /**
     * Ask a person with both profiles which one to act as. Everyone else has
     * nothing to choose.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        $profiles = $this->activeProfile->available($request->user());

        if (count($profiles) < 2) {
            return to_route('home');
        }

        return Inertia::render('auth/ChooseProfile', [
            'profiles' => array_map(fn (Profile $profile) => $profile->value, $profiles),
        ]);
    }

    /**
     * Choose or switch profile. The home depends on the profile, so go there
     * instead of back to a page the new profile may not be allowed to see.
     */
    public function update(SwitchProfileRequest $request): RedirectResponse
    {
        $this->activeProfile->choose($request->profile());

        return to_route('home');
    }
}
