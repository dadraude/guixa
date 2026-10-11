<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\ActiveProfile;
use App\Auth\Profile;
use App\Auth\WaitingForHousehold;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activeProfile = app(ActiveProfile::class);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // Without relations: the loaded member and household are not for the frontend.
                'user' => $user?->withoutRelations(),
                'isOwner' => $user !== null && $activeProfile->isActingAsOwner($user),
                'canManageMembership' => $user !== null && $user->can('manage-membership'),
                'isWaiting' => $user !== null && app(WaitingForHousehold::class)->isWaiting($user),
                'actingAs' => $user === null ? null : $activeProfile->current($user)?->value,
                'profiles' => $user === null ? [] : array_map(fn (Profile $profile) => $profile->value, $activeProfile->available($user)),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
