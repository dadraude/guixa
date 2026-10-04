<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    private const array OWNER_ONLY_ABILITIES = ['manage-administrators'];

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
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * An active administrator passes every authorization check, except the
     * owner-only abilities, which are decided by their own definition.
     * Abilities reserved to administrators are defined as false for everyone else.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user, string $ability) => $user->isActingAsAdministrator() && ! in_array($ability, self::OWNER_ONLY_ABILITIES, true)
            ? true
            : null);

        Gate::define('administer', fn (User $user) => false);
        // The single rule behind every household and member management check;
        // the roles ticket widens it to the membership role.
        Gate::define('manage-membership', fn (User $user) => false);
        Gate::define('manage-administrators', fn (User $user) => $user->isOwner());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
