<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Guixa\Catalog\Domain\ProducerAccess;
use Guixa\Catalog\Domain\ProducerCycles;
use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Catalog\Infrastructure\Membership\MembershipProducerAccess;
use Guixa\Catalog\Infrastructure\Ordering\OrderingProducerCycles;
use Guixa\Catalog\Infrastructure\Persistence\EloquentProducerRepository;
use Guixa\Catalog\Infrastructure\Persistence\EloquentProductRepository;
use Guixa\Membership\Domain\AccountPasswords;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Membership\Domain\InvitationRepository;
use Guixa\Membership\Domain\InvitationSender;
use Guixa\Membership\Domain\ProducerDirectory;
use Guixa\Membership\Infrastructure\Catalog\CatalogProducerDirectory;
use Guixa\Membership\Infrastructure\Mail\MailInvitationSender;
use Guixa\Membership\Infrastructure\Persistence\DatabaseAccountPasswords;
use Guixa\Membership\Infrastructure\Persistence\DatabaseAccountRepository;
use Guixa\Membership\Infrastructure\Persistence\DatabaseHouseholdRepository;
use Guixa\Membership\Infrastructure\Persistence\DatabaseInvitationRepository;
use Guixa\Ordering\Domain\CooperativeSettings;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Households;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProducerCutoffRules;
use Guixa\Ordering\Domain\ProducerNotifier;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Ordering\Domain\ReceivedTotalRepository;
use Guixa\Ordering\Infrastructure\Catalog\CatalogProducerCutoffRules;
use Guixa\Ordering\Infrastructure\Catalog\CatalogProductCatalog;
use Guixa\Ordering\Infrastructure\Membership\MembershipHouseholds;
use Guixa\Ordering\Infrastructure\Membership\MembershipProducerNotifier;
use Guixa\Ordering\Infrastructure\Persistence\EloquentCooperativeSettings;
use Guixa\Ordering\Infrastructure\Persistence\EloquentCycleRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentOrderRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentProductAvailabilityRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentReceivedTotalRepository;
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
use Guixa\Wallet\Domain\MovementRepository;
use Guixa\Wallet\Infrastructure\Persistence\EloquentMovementRepository;
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
        $this->app->bind(MovementRepository::class, EloquentMovementRepository::class);

        $this->app->bind(HouseholdRepository::class, DatabaseHouseholdRepository::class);
        $this->app->bind(AccountRepository::class, DatabaseAccountRepository::class);
        $this->app->bind(InvitationRepository::class, DatabaseInvitationRepository::class);
        $this->app->bind(InvitationSender::class, MailInvitationSender::class);
        $this->app->bind(AccountPasswords::class, DatabaseAccountPasswords::class);
        $this->app->bind(ProducerDirectory::class, CatalogProducerDirectory::class);

        $this->app->bind(ProducerRepository::class, EloquentProducerRepository::class);
        $this->app->bind(ProductRepository::class, EloquentProductRepository::class);
        $this->app->bind(ProducerCycles::class, OrderingProducerCycles::class);
        $this->app->bind(ProducerAccess::class, MembershipProducerAccess::class);

        $this->app->bind(CooperativeSettings::class, EloquentCooperativeSettings::class);
        $this->app->bind(CycleRepository::class, EloquentCycleRepository::class);
        $this->app->bind(ProducerCutoffRules::class, CatalogProducerCutoffRules::class);
        $this->app->bind(OrderRepository::class, EloquentOrderRepository::class);
        $this->app->bind(ProductCatalog::class, CatalogProductCatalog::class);
        $this->app->bind(ProductAvailabilityRepository::class, EloquentProductAvailabilityRepository::class);
        $this->app->bind(ReceivedTotalRepository::class, EloquentReceivedTotalRepository::class);
        $this->app->bind(Households::class, MembershipHouseholds::class);
        $this->app->bind(ProducerNotifier::class, MembershipProducerNotifier::class);
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
