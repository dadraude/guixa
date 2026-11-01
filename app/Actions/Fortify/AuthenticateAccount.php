<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Guixa\Membership\Application\CanLogIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

/** Login needs the right password and an account that accepted its invitation and is not deactivated (R34, R36b). */
final class AuthenticateAccount
{
    public function __construct(private readonly CanLogIn $canLogIn) {}

    public function __invoke(Request $request): ?User
    {
        $user = User::query()->whereRaw('lower(email) = ?', [Str::lower((string) $request->input(Fortify::username()))])->first();

        $provider = Auth::guard(config('fortify.guard'))->getProvider();
        $credentials = ['password' => (string) $request->input('password')];

        if ($user === null || ! $provider->validateCredentials($user, $credentials)) {
            return null;
        }

        if (! ($this->canLogIn)($user->id)) {
            return null;
        }

        if (config('hashing.rehash_on_login', true)) {
            $provider->rehashPasswordIfRequired($user, $credentials);
        }

        return $user;
    }
}
