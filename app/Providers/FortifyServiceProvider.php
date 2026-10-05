<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\MagicLinkBroker;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    private const int MINIMUM_SIGN_IN_MICROSECONDS = 200_000;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureViews();
        $this->configureAuthentication();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'status' => $request->session()->get('status'),
        ]));
    }

    /**
     * Password sign-in refuses people who may not sign in, such as inactive
     * administrators, with the same error as a wrong password. Every attempt
     * takes at least as long as Laravel's own guard, so response time does
     * not reveal whether the email exists either.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(fn (Request $request): ?User => app(Timebox::class)->call(
            fn (Timebox $timebox): ?User => $this->authenticate($request, $timebox),
            self::MINIMUM_SIGN_IN_MICROSECONDS,
        ));
    }

    private function authenticate(Request $request, Timebox $timebox): ?User
    {
        $user = User::where('email', Str::lower((string) $request->input(Fortify::username())))->first();

        if ($user === null || $user->password === null || ! $user->canSignIn()) {
            return null;
        }

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            return null;
        }

        $timebox->returnEarly();

        return $user;
    }

    /**
     * Configure rate limiting. Password attempts, magic-link requests and
     * code attempts share one limit per email and one per IP address. The
     * code form counts against the email the session asked a link for,
     * whatever the request sends.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = $request->routeIs('magic-link.code.store')
                ? $request->session()->get(MagicLinkBroker::EMAIL_SESSION_KEY)
                : $request->input(Fortify::username());
            $throttleKey = Str::transliterate(Str::lower(is_string($email) ? $email : '').'|'.$request->ip());

            return [
                Limit::perMinute(5)->by($throttleKey),
                Limit::perMinute(20)->by('ip|'.$request->ip()),
            ];
        });
    }
}
