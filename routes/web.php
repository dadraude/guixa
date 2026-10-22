<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\Manage\HouseholdController;
use App\Http\Controllers\Manage\HouseholdMemberController;
use App\Http\Controllers\Manage\HouseholdWalletController;
use App\Http\Controllers\Manage\InvitationController;
use App\Http\Controllers\Manage\ReturnItemController;
use App\Http\Controllers\Manage\StockProductController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:member'])->get('wallet', [WalletController::class, 'show'])->name('wallet.show');

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
    Route::resource('return-items', ReturnItemController::class)
        ->parameters(['return-items' => 'returnItem'])
        ->except(['show'])
        ->whereUuid('returnItem')
        ->names('return-items');

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

    Route::post('accounts/{account}/invitation', [InvitationController::class, 'resend'])
        ->whereUuid('account')
        ->name('accounts.invitation.resend');
    Route::put('accounts/{account}/invitation', [InvitationController::class, 'changeEmail'])
        ->whereUuid('account')
        ->name('accounts.invitation.email');
});

require __DIR__.'/settings.php';
