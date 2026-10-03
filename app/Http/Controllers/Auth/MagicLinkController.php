<?php

namespace App\Http\Controllers\Auth;

use App\Auth\MagicLinkBroker;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MagicLinkController extends Controller
{
    /**
     * Sign in the device that opened the link.
     */
    public function show(Request $request, MagicLinkBroker $magicLinks, string $token): RedirectResponse
    {
        $user = $magicLinks->consume($token);

        if ($user === null) {
            return to_route('login')->with('status', __('This link has expired or has already been used.'));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}
