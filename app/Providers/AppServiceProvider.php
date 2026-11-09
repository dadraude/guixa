<?php

namespace App\Providers;

use App\Policies\HouseholdPolicy;
use App\Policies\ProducerPolicy;
use App\Policies\ProductPolicy;
use Carbon\CarbonImmutable;
use Guixa\Catalog\Domain\ProducerAccess;
use Guixa\Catalog\Domain\ProducerCycles;
use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Catalog\Domain\ProductUsage;
use Guixa\Catalog\Domain\StockAvailability;
use Guixa\Catalog\Infrastructure\Membership\MembershipProducerAccess;
use Guixa\Catalog\Infrastructure\Ordering\OrderingProducerCycles;
use Guixa\Catalog\Infrastructure\Ordering\RetainedProductUsage;
use Guixa\Catalog\Infrastructure\Persistence\EloquentProducerRepository;
use Guixa\Catalog\Infrastructure\Persistence\EloquentProductRepository;
use Guixa\Catalog\Infrastructure\Stock\StockAvailabilityAdapter;
use Guixa\Membership\Domain\AccountPasswords;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\HouseholdBalances;
use Guixa\Membership\Domain\HouseholdBaskets;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Membership\Domain\InvitationRepository;
use Guixa\Membership\Domain\InvitationSender;
use Guixa\Membership\Domain\ProducerDirectory;
use Guixa\Membership\Domain\RoleChangeLog;
use Guixa\Membership\Infrastructure\Catalog\CatalogProducerDirectory;
use Guixa\Membership\Infrastructure\Mail\MailInvitationSender;
use Guixa\Membership\Infrastructure\Persistence\DatabaseAccountPasswords;
use Guixa\Membership\Infrastructure\Persistence\DatabaseAccountRepository;
use Guixa\Membership\Infrastructure\Persistence\DatabaseHouseholdRepository;
use Guixa\Membership\Infrastructure\Persistence\DatabaseInvitationRepository;
use Guixa\Membership\Infrastructure\Persistence\DatabaseRoleChangeLog;
use Guixa\Membership\Infrastructure\Sales\SalesHouseholdBaskets;
use Guixa\Membership\Infrastructure\Wallet\WalletHouseholdBalances;
use Guixa\Ordering\Application\SalesBaskets;
use Guixa\Ordering\Domain\CooperativeSettings;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Households;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProducerCutoffRules;
use Guixa\Ordering\Domain\ProducerNotifier;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Ordering\Domain\ReceivedTotalRepository;
use Guixa\Ordering\Domain\ReceptionHouseholds;
use Guixa\Ordering\Domain\ReceptionProducerNames;
use Guixa\Ordering\Domain\WeeklyBasketView;
use Guixa\Ordering\Infrastructure\Catalog\CatalogProducerCutoffRules;
use Guixa\Ordering\Infrastructure\Catalog\CatalogProductCatalog;
use Guixa\Ordering\Infrastructure\Catalog\CatalogReceptionProducerNames;
use Guixa\Ordering\Infrastructure\Membership\MembershipHouseholds;
use Guixa\Ordering\Infrastructure\Membership\MembershipProducerNotifier;
use Guixa\Ordering\Infrastructure\Membership\MembershipReceptionHouseholds;
use Guixa\Ordering\Infrastructure\Persistence\EloquentCooperativeSettings;
use Guixa\Ordering\Infrastructure\Persistence\EloquentCycleRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentOrderRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentProductAvailabilityRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentReceivedTotalRepository;
use Guixa\Ordering\Infrastructure\Sales\SalesModuleBaskets;
use Guixa\Sales\Application\StockCorrector as BasketStockCorrector;
use Guixa\Sales\Application\StockSeller as BasketStockSeller;
use Guixa\Sales\Application\WalletLedger as BasketWalletLedger;
use Guixa\Sales\Application\WeeklyBasketSource;
use Guixa\Sales\Application\WeeklySourceOrders;
use Guixa\Sales\Domain\AccountNames as SalesAccountNames;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\CyclePickupDates;
use Guixa\Sales\Domain\HouseholdDirectory;
use Guixa\Sales\Domain\ReturnItems;
use Guixa\Sales\Domain\StockOfferCatalog;
use Guixa\Sales\Domain\StockProducts;
use Guixa\Sales\Domain\StockQuantities;
use Guixa\Sales\Infrastructure\Catalog\CatalogStockOfferCatalog;
use Guixa\Sales\Infrastructure\Catalog\CatalogStockProducts;
use Guixa\Sales\Infrastructure\Membership\MembershipAccountNames as SalesMembershipAccountNames;
use Guixa\Sales\Infrastructure\Membership\MembershipHouseholdDirectory;
use Guixa\Sales\Infrastructure\Ordering\OrderingCyclePickupDates;
use Guixa\Sales\Infrastructure\Ordering\OrderingWeeklyBasketSource;
use Guixa\Sales\Infrastructure\Ordering\OrderingWeeklySourceOrders;
use Guixa\Sales\Infrastructure\Persistence\EloquentBasketRepository;
use Guixa\Sales\Infrastructure\Stock\StockBasketCorrector;
use Guixa\Sales\Infrastructure\Stock\StockBasketSeller;
use Guixa\Sales\Infrastructure\Stock\StockModuleReturnItems;
use Guixa\Sales\Infrastructure\Stock\StockQuantitiesAdapter;
use Guixa\Sales\Infrastructure\Wallet\WalletBasketLedger;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\ActorResolver;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Role;
use Guixa\Shared\Infrastructure\DatabaseActorResolver;
use Guixa\Shared\Infrastructure\DatabaseTransactionRunner;
use Guixa\Shared\Infrastructure\SystemClock;
use Guixa\Stock\Domain\AccountNames as StockAccountNames;
use Guixa\Stock\Domain\CyclePickupDates as StockCyclePickupDates;
use Guixa\Stock\Domain\ReturnItemRepository;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\StockProductRepository;
use Guixa\Stock\Infrastructure\Catalog\CatalogStockProductRepository;
use Guixa\Stock\Infrastructure\Membership\MembershipAccountNames;
use Guixa\Stock\Infrastructure\Ordering\OrderingCyclePickupDates as StockOrderingCyclePickupDates;
use Guixa\Stock\Infrastructure\Persistence\EloquentReturnItemRepository;
use Guixa\Stock\Infrastructure\Persistence\EloquentStockMovementRepository;
use Guixa\Wallet\Domain\AccountNames as WalletAccountNames;
use Guixa\Wallet\Domain\MovementRepository;
use Guixa\Wallet\Infrastructure\Membership\MembershipAccountNames as WalletMembershipAccountNames;
use Guixa\Wallet\Infrastructure\Persistence\EloquentMovementRepository;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        $this->app->bind(StockProductRepository::class, CatalogStockProductRepository::class);
        $this->app->bind(ReturnItemRepository::class, EloquentReturnItemRepository::class);
        $this->app->bind(StockMovementRepository::class, EloquentStockMovementRepository::class);
        $this->app->bind(StockAccountNames::class, MembershipAccountNames::class);
        $this->app->bind(SalesAccountNames::class, SalesMembershipAccountNames::class);
        $this->app->bind(WalletAccountNames::class, WalletMembershipAccountNames::class);
        $this->app->bind(StockCyclePickupDates::class, StockOrderingCyclePickupDates::class);
        $this->app->bind(StockAvailability::class, StockAvailabilityAdapter::class);
        $this->app->bind(StockOfferCatalog::class, CatalogStockOfferCatalog::class);
        $this->app->bind(StockQuantities::class, StockQuantitiesAdapter::class);
        $this->app->bind(BasketRepository::class, EloquentBasketRepository::class);
        $this->app->bind(ReturnItems::class, StockModuleReturnItems::class);
        $this->app->bind(HouseholdDirectory::class, MembershipHouseholdDirectory::class);
        $this->app->bind(StockProducts::class, CatalogStockProducts::class);
        $this->app->bind(BasketStockSeller::class, StockBasketSeller::class);
        $this->app->bind(BasketStockCorrector::class, StockBasketCorrector::class);
        $this->app->bind(BasketWalletLedger::class, WalletBasketLedger::class);
        $this->app->bind(WeeklyBasketSource::class, OrderingWeeklyBasketSource::class);
        $this->app->bind(WeeklySourceOrders::class, OrderingWeeklySourceOrders::class);
        $this->app->bind(CyclePickupDates::class, OrderingCyclePickupDates::class);
        $this->app->bind(MovementRepository::class, EloquentMovementRepository::class);

        $this->app->bind(HouseholdRepository::class, DatabaseHouseholdRepository::class);
        $this->app->bind(HouseholdBalances::class, WalletHouseholdBalances::class);
        $this->app->bind(HouseholdBaskets::class, SalesHouseholdBaskets::class);
        $this->app->bind(AccountRepository::class, DatabaseAccountRepository::class);
        $this->app->bind(RoleChangeLog::class, DatabaseRoleChangeLog::class);
        $this->app->bind(InvitationRepository::class, DatabaseInvitationRepository::class);
        $this->app->bind(InvitationSender::class, MailInvitationSender::class);
        $this->app->bind(AccountPasswords::class, DatabaseAccountPasswords::class);
        $this->app->bind(ProducerDirectory::class, CatalogProducerDirectory::class);

        $this->app->bind(ProducerRepository::class, EloquentProducerRepository::class);
        $this->app->bind(ProductRepository::class, EloquentProductRepository::class);
        $this->app->bind(ProductUsage::class, RetainedProductUsage::class);
        $this->app->bind(ProducerCycles::class, OrderingProducerCycles::class);
        $this->app->bind(ProducerAccess::class, MembershipProducerAccess::class);

        $this->app->bind(CooperativeSettings::class, EloquentCooperativeSettings::class);
        $this->app->bind(CycleRepository::class, EloquentCycleRepository::class);
        $this->app->bind(ProducerCutoffRules::class, CatalogProducerCutoffRules::class);
        $this->app->bind(OrderRepository::class, EloquentOrderRepository::class);
        $this->app->bind(ProductCatalog::class, CatalogProductCatalog::class);
        $this->app->bind(SalesBaskets::class, SalesModuleBaskets::class);
        $this->app->bind(WeeklyBasketView::class, SalesModuleBaskets::class);
        $this->app->bind(ProductAvailabilityRepository::class, EloquentProductAvailabilityRepository::class);
        $this->app->bind(ReceivedTotalRepository::class, EloquentReceivedTotalRepository::class);
        $this->app->bind(Households::class, MembershipHouseholds::class);
        $this->app->bind(ReceptionHouseholds::class, MembershipReceptionHouseholds::class);
        $this->app->bind(ReceptionProducerNames::class, CatalogReceptionProducerNames::class);
        $this->app->bind(ProducerNotifier::class, MembershipProducerNotifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage', fn (Actor $actor): bool => $actor->hasRole(Role::Manager));
        Gate::define('receive', fn (Actor $actor): bool => $actor->hasAnyRole(Role::Manager->value, Role::Finance->value, Role::Receiver->value));
        Gate::define('settle-reception', fn (Actor $actor): bool => $actor->hasRole(Role::Manager));
        Gate::define('manage-roles', fn (Actor $actor): bool => $actor->hasRole(Role::Manager));
        Gate::define('administer-catalogue', fn (Actor $actor): bool => $actor->hasAnyRole(Role::Manager->value, Role::Catalogue->value));
        Gate::define('view-producers', fn (Actor $actor): bool => $actor->hasAnyRole(Role::Manager->value, Role::Catalogue->value));
        Gate::define('view-stock', fn (Actor $actor): bool => $actor->hasAnyRole(Role::Manager->value, Role::Finance->value, Role::Catalogue->value));
        Gate::define('view-return-items', fn (Actor $actor): bool => $actor->hasAnyRole(Role::Manager->value, Role::Finance->value, Role::Catalogue->value));
        Gate::define('staff-role', fn (Actor $actor, string $role): bool => Role::tryFrom($role)?->isStaff() ?? false);
        Gate::define('manage-sales', fn (Actor $actor): bool => $actor->hasAnyRole(Role::Manager->value, Role::Finance->value));
        Gate::define('manage-wallet', fn (Actor $actor): bool => $actor->hasAnyRole(Role::Manager->value, Role::Finance->value));
        Gate::define('view-stock-offers', fn (Actor $actor): bool => $actor->hasAnyRole(Role::Member->value, Role::Manager->value));
        Gate::define('producer', fn (Actor $actor): bool => $actor->hasRole(Role::Producer) && $actor->producerId !== null);
        Gate::define('role', fn (Actor $actor, array $roles): bool => $actor->hasAnyRole(...$roles));
        Gate::policy('household', HouseholdPolicy::class);
        Gate::policy('producer', ProducerPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

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
