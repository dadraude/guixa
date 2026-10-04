<?php

use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Households\HouseholdController;
use App\Http\Controllers\Households\InvitationController;
use App\Http\Controllers\Invitations\AcceptInvitationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('magic-link', [MagicLinkController::class, 'store'])
        ->middleware('throttle:login')
        ->name('magic-link.store');
    Route::get('magic-link/code', [MagicLinkController::class, 'code'])->name('magic-link.code');
    Route::post('magic-link/code', [MagicLinkController::class, 'storeCode'])
        ->middleware('throttle:login')
        ->name('magic-link.code.store');
    Route::get('magic-link/{token}', [MagicLinkController::class, 'show'])->name('magic-link.show');
    Route::post('magic-link/{token}', [MagicLinkController::class, 'consume'])->name('magic-link.consume');
});

Route::middleware('guest')->prefix('invitations')->name('invitations.')->group(function () {
    Route::get('{token}', [AcceptInvitationController::class, 'show'])->name('show');
    Route::post('{token}', [AcceptInvitationController::class, 'accept'])->name('accept');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/', function (Request $request) {
        return $request->user()?->isActingAsAdministrator()
            ? to_route('admin.home')
            : to_route('dashboard');
    })->name('home');

    Route::get('set-password', [SetPasswordController::class, 'create'])->name('password.setup');
    Route::post('set-password', [SetPasswordController::class, 'store'])->name('password.setup.store');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('can:manage-membership')->prefix('households')->name('households.')->group(function () {
        Route::get('/', [HouseholdController::class, 'index'])->name('index');
        Route::post('/', [HouseholdController::class, 'store'])->name('store');
        Route::patch('{household}', [HouseholdController::class, 'update'])->name('update');
        Route::post('{household}/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    });

    Route::middleware('can:administer')->prefix('admin')->name('admin.')->group(function () {
        Route::inertia('/', 'admin/Home')->name('home');

        Route::middleware('can:manage-administrators')->prefix('administrators')->name('administrators.')->group(function () {
            Route::get('/', [AdministratorController::class, 'index'])->name('index');
            Route::post('/', [AdministratorController::class, 'store'])->name('store');
            Route::post('{administrator}/deactivate', [AdministratorController::class, 'deactivate'])->name('deactivate');
            Route::post('{administrator}/reactivate', [AdministratorController::class, 'reactivate'])->name('reactivate');
        });
    });
});

require __DIR__.'/settings.php';
