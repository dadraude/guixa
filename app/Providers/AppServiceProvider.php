<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\ActiveProfile;
use App\Auth\MemberRoles;
use App\Auth\MembershipRoleProtection;
use App\Auth\Role;
use App\Auth\UsableMember;
use App\Auth\UserSessions;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $this->app->scoped(MemberRoles::class);
        $this->app->scoped(UsableMember::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->stampSessionsOnSignIn();
    }

    /**
     * Every sign-in, including the one a remember cookie triggers, stamps its
     * session with the user's session version (see UserSessions).
     */
    protected function stampSessionsOnSignIn(): void
    {
        Event::listen(function (Login $event): void {
            $guard = Auth::guard($event->guard);

            if (! $guard instanceof SessionGuard || ! $event->user instanceof User) {
                return;
            }

            app(UserSessions::class)->stamp($guard->getSession(), $event->user);
        });
    }

    /**
     * Someone acting as an active administrator passes every authorization
     * check, except the owner-only abilities, which are decided by their own
     * definition. Acting as member never passes, even for an administrator.
     * Abilities reserved to administrators are defined as false for everyone else.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user, string $ability) => app(ActiveProfile::class)->isActingAsAdministrator($user) && ! in_array($ability, self::OWNER_ONLY_ABILITIES, true)
            ? true
            : null);

        Gate::define('administer', fn (User $user) => false);
        // The single rule behind every household and member management check.
        Gate::define('manage-membership', fn (User $user) => app(MemberRoles::class)->userHas($user, Role::Membership));
        // Only administrators assign roles (they pass Gate::before), so the
        // membership role can never grant itself or others more permissions.
        Gate::define('assign-roles', fn (User $user) => false);
        // Only administrators take the membership role away from a member (see MembershipRoleProtection).
        Gate::define(MembershipRoleProtection::ABILITY, fn (User $user) => false);
        // Every active member sees the commissions and who is in them.
        Gate::define('view-commissions', fn (User $user) => $user->member?->is_active === true);
        // Every active member opens the directory; what they see depends on
        // whether they are in a commission (see DirectoryController).
        Gate::define('view-directory', fn (User $user) => $user->member?->is_active === true);
        Gate::define('manage-administrators', fn (User $user) => app(ActiveProfile::class)->isActingAsOwner($user));
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
