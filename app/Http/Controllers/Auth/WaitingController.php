<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\WaitingForHousehold;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WaitingController extends Controller
{
    /**
     * Only someone still waiting sees it; once they have an active household or a
     * commission there is nothing to wait for.
     */
    public function __invoke(#[CurrentUser] User $user, WaitingForHousehold $waitingForHousehold): Response|RedirectResponse
    {
        if (! $waitingForHousehold->isWaiting($user)) {
            return to_route('home');
        }

        return Inertia::render('auth/Waiting');
    }
}
