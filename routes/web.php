<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\Manage\AccountController;
use App\Http\Controllers\Manage\AccountDeactivationController;
use App\Http\Controllers\Manage\AccountHouseholdController;
use App\Http\Controllers\Manage\CycleController;
use App\Http\Controllers\Manage\CycleCutoffController;
use App\Http\Controllers\Manage\CycleParticipantController;
use App\Http\Controllers\Manage\HouseholdController;
use App\Http\Controllers\Manage\HouseholdMemberController;
use App\Http\Controllers\Manage\HouseholdWalletController;
use App\Http\Controllers\Manage\InvitationController;
use App\Http\Controllers\Manage\ManagerRoleController;
use App\Http\Controllers\Manage\ProducerAccountController;
use App\Http\Controllers\Manage\ProducerController;
use App\Http\Controllers\Manage\ReturnItemController;
use App\Http\Controllers\Manage\SettingsController;
use App\Http\Controllers\Manage\StockMovementController;
use App\Http\Controllers\Manage\StockProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Producer\CutoffController;
use App\Http\Controllers\Producer\ProductAvailabilityController;
use App\Http\Controllers\Producer\ProductController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:member'])->get('wallet', [WalletController::class, 'show'])->name('wallet.show');

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
    Route::resource('stock', StockProductController::class)
        ->parameters(['stock' => 'stockProduct'])
        ->except(['show'])
        ->whereUuid('stockProduct')
        ->names('stock');
    Route::get('stock/{stockProduct}/movements', [StockMovementController::class, 'show'])
        ->whereUuid('stockProduct')
        ->name('stock.movements.show');
    Route::post('stock/{stockProduct}/entries', [StockMovementController::class, 'storeEntry'])
        ->whereUuid('stockProduct')
        ->name('stock.entries.store');
    Route::post('stock/{stockProduct}/adjustments', [StockMovementController::class, 'storeAdjustment'])
        ->whereUuid('stockProduct')
        ->name('stock.adjustments.store');
    Route::resource('return-items', ReturnItemController::class)
        ->parameters(['return-items' => 'returnItem'])
        ->except(['show'])
        ->whereUuid('returnItem')
        ->names('return-items');

    Route::get('cycles', [CycleController::class, 'index'])->name('cycles.index');
    Route::post('cycles', [CycleController::class, 'store'])->name('cycles.store');
    Route::get('cycles/{cycle}', [CycleController::class, 'show'])->whereUuid('cycle')->name('cycles.show');
    Route::post('cycles/{cycle}/producers/{producer}', [CycleParticipantController::class, 'store'])
        ->whereUuid(['cycle', 'producer'])
        ->name('cycles.producers.store');
    Route::prefix('cycles/{cycle}/producers/{producer}/cutoff')->whereUuid(['cycle', 'producer'])->group(function () {
        Route::put('/', [CycleCutoffController::class, 'update'])->name('cycles.producers.cutoff.update');
        Route::post('close', [CycleCutoffController::class, 'close'])->name('cycles.producers.cutoff.close');
        Route::delete('/', [CycleCutoffController::class, 'destroy'])->name('cycles.producers.cutoff.destroy');
    });

    Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::put('settings/pickup-weekday', [SettingsController::class, 'updatePickupWeekday'])->name('settings.pickup-weekday.update');

    Route::get('households', [HouseholdController::class, 'index'])->name('households.index');
    Route::post('households', [HouseholdController::class, 'store'])->name('households.store');
    Route::get('households/{household}', [HouseholdController::class, 'show'])->whereUuid('household')->name('households.show');
    Route::put('households/{household}', [HouseholdController::class, 'update'])->whereUuid('household')->name('households.update');
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
    Route::put('products/{product}/reactivation', [ProductController::class, 'reactivate'])
        ->whereUuid('product')
        ->name('products.reactivate');
    Route::put('cycles/{cycle}/products/{product}/availability', [ProductAvailabilityController::class, 'update'])
        ->whereUuid(['cycle', 'product'])
        ->name('products.availability');
});

require __DIR__.'/settings.php';
