<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Guixa\Shared\Application\ActorResolver;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Infrastructure\DatabaseActorResolver;
use Guixa\Shared\Infrastructure\DatabaseTransactionRunner;
use Guixa\Shared\Infrastructure\SystemClock;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Clock::class, fn (): Clock => new SystemClock(config('guixa.timezone')));
        $this->app->bind(TransactionRunner::class, DatabaseTransactionRunner::class);
        $this->app->bind(ActorResolver::class, DatabaseActorResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDevCommands();
    }

    /**
     * Run the scheduler alongside `php artisan dev`; production uses a cron entry instead.
     */
    protected function configureDevCommands(): void
    {
        DevCommands::artisan('schedule:work', 'scheduler');
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
