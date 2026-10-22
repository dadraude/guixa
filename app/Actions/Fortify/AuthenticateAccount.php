<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Guixa\Membership\Application\CanLogIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

/** Login needs the right password and an account that accepted its invitation and is not deactivated (R34, R36b). */
final class AuthenticateAccount
{
    public function __construct(private readonly CanLogIn $canLogIn) {}

    public function __invoke(Request $request): ?User
    {
        $user = User::query()->whereRaw('lower(email) = ?', [Str::lower((string) $request->input(Fortify::username()))])->first();

        if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
            return null;
        }

        return ($this->canLogIn)($user->id) ? $user : null;
    }
}
