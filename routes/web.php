<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\Manage\AccountController;
use App\Http\Controllers\Manage\AccountDeactivationController;
use App\Http\Controllers\Manage\AccountHouseholdController;
use App\Http\Controllers\Manage\HouseholdController;
use App\Http\Controllers\Manage\HouseholdMemberController;
use App\Http\Controllers\Manage\InvitationController;
use App\Http\Controllers\Manage\ManagerRoleController;
use App\Http\Controllers\Manage\ReturnItemController;
use App\Http\Controllers\Manage\StockProductController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
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

require __DIR__.'/settings.php';
