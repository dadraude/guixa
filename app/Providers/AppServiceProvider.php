<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Guixa\Membership\Domain\AccountPasswords;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Membership\Domain\InvitationRepository;
use Guixa\Membership\Domain\InvitationSender;
use Guixa\Membership\Infrastructure\Mail\MailInvitationSender;
use Guixa\Membership\Infrastructure\Persistence\DatabaseAccountPasswords;
use Guixa\Membership\Infrastructure\Persistence\DatabaseAccountRepository;
use Guixa\Membership\Infrastructure\Persistence\DatabaseHouseholdRepository;
use Guixa\Membership\Infrastructure\Persistence\DatabaseInvitationRepository;
use Guixa\Shared\Application\ActorResolver;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Infrastructure\DatabaseActorResolver;
use Guixa\Shared\Infrastructure\DatabaseTransactionRunner;
use Guixa\Shared\Infrastructure\SystemClock;
use Guixa\Stock\Domain\ReturnItemRepository;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\StockProductRepository;
use Guixa\Stock\Infrastructure\Persistence\EloquentReturnItemRepository;
use Guixa\Stock\Infrastructure\Persistence\EloquentStockMovementRepository;
use Guixa\Stock\Infrastructure\Persistence\EloquentStockProductRepository;
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
        $this->app->bind(StockProductRepository::class, EloquentStockProductRepository::class);
        $this->app->bind(ReturnItemRepository::class, EloquentReturnItemRepository::class);
        $this->app->bind(StockMovementRepository::class, EloquentStockMovementRepository::class);

        $this->app->bind(HouseholdRepository::class, DatabaseHouseholdRepository::class);
        $this->app->bind(AccountRepository::class, DatabaseAccountRepository::class);
        $this->app->bind(InvitationRepository::class, DatabaseInvitationRepository::class);
        $this->app->bind(InvitationSender::class, MailInvitationSender::class);
        $this->app->bind(AccountPasswords::class, DatabaseAccountPasswords::class);
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
