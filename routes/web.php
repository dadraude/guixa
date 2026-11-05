<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\BasketCollectionController;
use App\Http\Controllers\Finance\BasketCollectionCorrectionController;
use App\Http\Controllers\Finance\BasketController;
use App\Http\Controllers\Finance\BasketCorrectionController;
use App\Http\Controllers\Finance\BasketReturnController;
use App\Http\Controllers\Finance\BasketStockController;
use App\Http\Controllers\Finance\BasketValidationController;
use App\Http\Controllers\Finance\BasketWeeklyLineController;
use App\Http\Controllers\Finance\HouseholdWalletController as FinanceHouseholdWalletController;
use App\Http\Controllers\Manage\AccountController;
use App\Http\Controllers\Manage\AccountDeactivationController;
use App\Http\Controllers\Manage\AccountHouseholdController;
use App\Http\Controllers\Manage\CycleCancellationController;
use App\Http\Controllers\Manage\CycleController;
use App\Http\Controllers\Manage\CycleCutoffController;
use App\Http\Controllers\Manage\CycleParticipantController;
use App\Http\Controllers\Manage\CycleProducerOrderController;
use App\Http\Controllers\Manage\FinanceRoleController;
use App\Http\Controllers\Manage\HouseholdController;
use App\Http\Controllers\Manage\HouseholdMemberController;
use App\Http\Controllers\Manage\HouseholdWalletController;
use App\Http\Controllers\Manage\InvitationController;
use App\Http\Controllers\Manage\ManagerRoleController;
use App\Http\Controllers\Manage\PickupController;
use App\Http\Controllers\Manage\ProducerAccountController;
use App\Http\Controllers\Manage\ProducerController;
use App\Http\Controllers\Manage\ProducerProductController;
use App\Http\Controllers\Manage\ProductClassificationController;
use App\Http\Controllers\Manage\ReceptionAllocationController;
use App\Http\Controllers\Manage\ReceptionController;
use App\Http\Controllers\Manage\ReceptionDistributionController;
use App\Http\Controllers\Manage\ReceptionProductController;
use App\Http\Controllers\Manage\ReceptionReceivedController;
use App\Http\Controllers\Manage\ReceptionValidationController;
use App\Http\Controllers\Manage\ReturnItemController;
use App\Http\Controllers\Manage\SettingsController;
use App\Http\Controllers\Manage\StockMovementController;
use App\Http\Controllers\Manage\StockProductController;
use App\Http\Controllers\Member\BasketCollectionController as MemberBasketCollectionController;
use App\Http\Controllers\Member\BasketController as MemberBasketController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Producer\CutoffController;
use App\Http\Controllers\Producer\CycleController as ProducerCycleController;
use App\Http\Controllers\Producer\ProductAvailabilityController;
use App\Http\Controllers\Producer\ProductController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OrderController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:member'])->prefix('baskets')->name('baskets.')->whereUuid('basket')->group(function () {
    Route::get('/', [MemberBasketController::class, 'index'])->name('index');
    Route::get('{basket}', [MemberBasketController::class, 'show'])->name('show');
    Route::put('{basket}/collection', [MemberBasketCollectionController::class, 'update'])->name('collect');
});

Route::middleware(['auth', 'verified', 'role:member'])->get('wallet', [WalletController::class, 'show'])->name('wallet.show');
Route::middleware(['auth', 'verified', 'role:member'])->get('stock', [StockController::class, 'index'])->name('stock.index');

Route::middleware(['auth', 'verified', 'role:manager,finance'])->prefix('finance/baskets')->name('finance.baskets.')->whereUuid(['basket', 'stockProduct', 'product'])->group(function () {
    Route::get('/', [BasketController::class, 'index'])->name('index');
    Route::get('create', [BasketController::class, 'create'])->name('create');
    Route::post('/', [BasketController::class, 'store'])->name('store');
    Route::get('{basket}', [BasketController::class, 'show'])->name('show');
    Route::post('{basket}/stock-lines', [BasketStockController::class, 'store'])->name('stock-lines.store');
    Route::put('{basket}/weekly-lines/{product}', [BasketWeeklyLineController::class, 'update'])->name('weekly-lines.update');
    Route::post('{basket}/return-lines', [BasketReturnController::class, 'store'])->name('return-lines.store');
    Route::delete('{basket}/return-lines/{returnItem}', [BasketReturnController::class, 'destroy'])->name('return-lines.destroy');
    Route::delete('{basket}/stock-lines/{stockProduct}', [BasketStockController::class, 'destroy'])->name('stock-lines.destroy');
    Route::post('{basket}/validation', [BasketValidationController::class, 'store'])->name('validate');
    Route::post('{basket}/corrections', [BasketCorrectionController::class, 'store'])->name('corrections.store');
    Route::put('{basket}/collection', [BasketCollectionController::class, 'update'])->name('collect');
    Route::post('{basket}/collection-corrections', [BasketCollectionCorrectionController::class, 'store'])->name('collection-corrections.store');
    Route::post('{basket}/cancellation', [BasketController::class, 'cancel'])->name('cancel');
});

Route::middleware(['auth', 'verified', 'role:manager,finance'])->get('finance/households/{household}/wallet', [FinanceHouseholdWalletController::class, 'show'])
    ->whereUuid('household')->name('finance.households.wallet.show');
Route::middleware(['auth', 'verified', 'role:manager,finance'])->prefix('finance/households/{household}/wallet')->whereUuid('household')->name('finance.households.wallet.')->group(function () {
    Route::post('top-ups', [FinanceHouseholdWalletController::class, 'topUp'])->name('top-ups.store');
    Route::post('corrections', [FinanceHouseholdWalletController::class, 'correct'])->name('corrections.store');
});

Route::middleware(['auth', 'verified', 'role:member'])->prefix('cycles/{cycle}/order')->whereUuid(['cycle', 'product'])->name('orders.')->group(function () {
    Route::get('/', [OrderController::class, 'edit'])->name('edit');
    Route::put('lines/{product}', [OrderController::class, 'updateLine'])->name('lines.update');
    Route::delete('lines/{product}', [OrderController::class, 'removeMarkedLine'])->name('lines.destroy');
});

Route::middleware('guest')->group(function () {
    Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitation.show');
    Route::post('invitations/{token}', [AcceptInvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitation.accept');
});

Route::middleware(['auth', 'verified', 'role:manager'])->prefix('manage')->name('manage.')->group(function () {
    Route::get('producers/{producer}/products', [ProducerProductController::class, 'index'])
        ->whereUuid('producer')->name('producer-products.index');
    Route::put('products/{product}/classification', [ProductClassificationController::class, 'update'])
        ->whereUuid('product')->name('products.classification.update');
    Route::get('producers/{producer}/products/create', [StockProductController::class, 'create'])
        ->whereUuid('producer')->name('stock.create');
    Route::post('producers/{producer}/products', [StockProductController::class, 'store'])
        ->whereUuid('producer')->name('stock.store');
    Route::get('stock', [StockProductController::class, 'index'])->name('stock.index');
    Route::get('producers/{producer}/products/{stockProduct}/edit', [StockProductController::class, 'edit'])
        ->whereUuid(['producer', 'stockProduct'])->name('stock.edit');
    Route::put('producers/{producer}/products/{stockProduct}', [StockProductController::class, 'update'])
        ->whereUuid(['producer', 'stockProduct'])->name('stock.update');
    Route::delete('producers/{producer}/products/{stockProduct}', [StockProductController::class, 'destroy'])
        ->whereUuid(['producer', 'stockProduct'])->name('stock.destroy');
    Route::delete('producers/{producer}/products/{stockProduct}/unused', [StockProductController::class, 'deleteUnused'])
        ->whereUuid(['producer', 'stockProduct'])->name('stock.unused.destroy');
    Route::put('producers/{producer}/products/{stockProduct}/reactivation', [StockProductController::class, 'reactivate'])
        ->whereUuid(['producer', 'stockProduct'])->name('stock.reactivate');
    Route::get('stock/{stockProduct}/movements', [StockMovementController::class, 'show'])
        ->whereUuid('stockProduct')
        ->name('stock.movements.show');
    Route::post('stock/{stockProduct}/entries', [StockMovementController::class, 'storeEntry'])
        ->whereUuid('stockProduct')
        ->name('stock.entries.store');
    Route::post('stock/{stockProduct}/adjustments', [StockMovementController::class, 'storeAdjustment'])
        ->whereUuid('stockProduct')
        ->name('stock.adjustments.store');
    Route::put('stock/{stockProduct}/available', [StockMovementController::class, 'setAvailable'])
        ->whereUuid('stockProduct')
        ->name('stock.available.update');
    Route::resource('return-items', ReturnItemController::class)
        ->parameters(['return-items' => 'returnItem'])
        ->except(['show'])
        ->whereUuid('returnItem')
        ->names('return-items');
    Route::put('return-items/{returnItem}/reactivation', [ReturnItemController::class, 'reactivate'])
        ->whereUuid('returnItem')
        ->name('return-items.reactivate');

    Route::get('cycles', [CycleController::class, 'index'])->name('cycles.index');
    Route::post('cycles', [CycleController::class, 'store'])->name('cycles.store');
    Route::get('cycles/{cycle}', [CycleController::class, 'show'])->whereUuid('cycle')->name('cycles.show');
    Route::post('cycles/{cycle}/cancel', [CycleCancellationController::class, 'store'])->whereUuid('cycle')->name('cycles.cancel');
    Route::post('cycles/{cycle}/producers/{producer}', [CycleParticipantController::class, 'store'])
        ->whereUuid(['cycle', 'producer'])
        ->name('cycles.producers.store');
    Route::put('cycles/{cycle}/producers/{producer}', [CycleParticipantController::class, 'update'])
        ->whereUuid(['cycle', 'producer'])
        ->name('cycles.producers.update');
    Route::delete('cycles/{cycle}/producers/{producer}', [CycleParticipantController::class, 'destroy'])
        ->whereUuid(['cycle', 'producer'])
        ->name('cycles.producers.destroy');
    Route::post('cycles/{cycle}/producers/{producer}/send', [CycleProducerOrderController::class, 'send'])
        ->whereUuid(['cycle', 'producer'])
        ->name('cycles.producers.send');
    Route::prefix('cycles/{cycle}/producers/{producer}/cutoff')->whereUuid(['cycle', 'producer'])->group(function () {
        Route::put('/', [CycleCutoffController::class, 'update'])->name('cycles.producers.cutoff.update');
        Route::post('close', [CycleCutoffController::class, 'close'])->name('cycles.producers.cutoff.close');
        Route::delete('/', [CycleCutoffController::class, 'destroy'])->name('cycles.producers.cutoff.destroy');
    });

    Route::prefix('cycles/{cycle}/receptions/{producer}')->whereUuid(['cycle', 'producer', 'product', 'household'])->group(function () {
        Route::get('/', [ReceptionController::class, 'show'])->name('cycles.receptions.show');
        Route::post('products', [ReceptionProductController::class, 'store'])->name('cycles.receptions.products.store');
        Route::put('products/{product}/received', [ReceptionReceivedController::class, 'update'])->name('cycles.receptions.received.update');
        Route::put('products/{product}/allocations/{household}', [ReceptionAllocationController::class, 'update'])->name('cycles.receptions.allocations.update');
        Route::post('validate', [ReceptionValidationController::class, 'store'])->name('cycles.receptions.validate');
        Route::post('distribution', [ReceptionDistributionController::class, 'store'])->name('cycles.receptions.distribution.store');
        Route::delete('distribution', [ReceptionDistributionController::class, 'destroy'])->name('cycles.receptions.distribution.destroy');
    });

    Route::prefix('cycles/{cycle}/pickups')->whereUuid(['cycle', 'household'])->group(function () {
        Route::get('/', [PickupController::class, 'index'])->name('cycles.pickups.index');
        Route::get('{household}', [PickupController::class, 'show'])->name('cycles.pickups.show');
    });

    Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::put('settings/pickup-weekday', [SettingsController::class, 'updatePickupWeekday'])->name('settings.pickup-weekday.update');

    Route::get('households', [HouseholdController::class, 'index'])->name('households.index');
    Route::post('households', [HouseholdController::class, 'store'])->name('households.store');
    Route::get('households/{household}', [HouseholdController::class, 'show'])->whereUuid('household')->name('households.show');
    Route::put('households/{household}', [HouseholdController::class, 'update'])->whereUuid('household')->name('households.update');
    Route::post('households/{household}/deactivate', [HouseholdController::class, 'deactivate'])->whereUuid('household')->name('households.deactivate');
    Route::post('households/{household}/reactivate', [HouseholdController::class, 'reactivate'])->whereUuid('household')->name('households.reactivate');
    Route::post('households/{household}/members', [HouseholdMemberController::class, 'store'])
        ->whereUuid('household')
        ->name('households.members.store');
    Route::get('households/{household}/wallet', [HouseholdWalletController::class, 'show'])
        ->whereUuid('household')
        ->name('households.wallet.show');
    Route::post('households/{household}/wallet/top-ups', [HouseholdWalletController::class, 'topUp'])
        ->whereUuid('household')
        ->name('households.wallet.top-ups.store');
    Route::post('households/{household}/wallet/corrections', [HouseholdWalletController::class, 'correct'])
        ->whereUuid('household')
        ->name('households.wallet.corrections.store');

    Route::get('producers', [ProducerController::class, 'index'])->name('producers.index');
    Route::post('producers', [ProducerController::class, 'store'])->name('producers.store');
    Route::post('producers/{producer}/account', [ProducerAccountController::class, 'store'])
        ->whereUuid('producer')
        ->name('producers.account.store');
    Route::post('producers/{producer}/deactivation', [ProducerController::class, 'deactivate'])
        ->whereUuid('producer')
        ->name('producers.deactivation.store');
    Route::delete('producers/{producer}/deactivation', [ProducerController::class, 'reactivate'])
        ->whereUuid('producer')
        ->name('producers.deactivation.destroy');

    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::put('accounts/{account}/household', [AccountHouseholdController::class, 'update'])
        ->whereUuid('account')
        ->name('accounts.household.update');
    Route::delete('accounts/{account}/household', [AccountHouseholdController::class, 'destroy'])
        ->whereUuid('account')
        ->name('accounts.household.destroy');
    Route::put('accounts/{account}/manager-role', [ManagerRoleController::class, 'update'])
        ->whereUuid('account')
        ->name('accounts.manager-role.update');
    Route::delete('accounts/{account}/manager-role', [ManagerRoleController::class, 'destroy'])
        ->whereUuid('account')
        ->name('accounts.manager-role.destroy');
    Route::put('accounts/{account}/finance-role', [FinanceRoleController::class, 'update'])
        ->whereUuid('account')->name('accounts.finance-role.update');
    Route::delete('accounts/{account}/finance-role', [FinanceRoleController::class, 'destroy'])
        ->whereUuid('account')->name('accounts.finance-role.destroy');
    Route::post('accounts/{account}/deactivation', [AccountDeactivationController::class, 'store'])
        ->whereUuid('account')
        ->name('accounts.deactivation.store');
    Route::delete('accounts/{account}/deactivation', [AccountDeactivationController::class, 'destroy'])
        ->whereUuid('account')
        ->name('accounts.deactivation.destroy');

    Route::post('accounts/{account}/invitation', [InvitationController::class, 'resend'])
        ->whereUuid('account')
        ->name('accounts.invitation.resend');
    Route::put('accounts/{account}/invitation', [InvitationController::class, 'changeEmail'])
        ->whereUuid('account')
        ->name('accounts.invitation.email');
});

Route::middleware(['auth', 'verified', 'role:producer'])->prefix('producer')->name('producer.')->group(function () {
    Route::get('cutoff', [CutoffController::class, 'show'])->name('cutoff.show');
    Route::put('cutoff', [CutoffController::class, 'update'])->name('cutoff.update');

    Route::resource('products', ProductController::class)
        ->except(['show'])
        ->whereUuid('product')
        ->names('products');
    Route::delete('products/{product}/unused', [ProductController::class, 'deleteUnused'])
        ->whereUuid('product')->name('products.unused.destroy');
    Route::put('products/{product}/reactivation', [ProductController::class, 'reactivate'])
        ->whereUuid('product')
        ->name('products.reactivate');
    Route::get('cycles/{cycle}', [ProducerCycleController::class, 'show'])
        ->whereUuid('cycle')
        ->name('cycles.show');
    Route::put('cycles/{cycle}/products/{product}/availability', [ProductAvailabilityController::class, 'update'])
        ->whereUuid(['cycle', 'product'])
        ->name('products.availability');
});

require __DIR__.'/settings.php';
