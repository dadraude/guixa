<?php

use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\SetPasswordController;
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

Route::middleware(['auth'])->group(function () {
    Route::get('/', function (Request $request) {
        return $request->user()?->isActingAsAdministrator()
            ? to_route('admin.home')
            : to_route('dashboard');
    })->name('home');

    Route::get('set-password', [SetPasswordController::class, 'create'])->name('password.setup');
    Route::post('set-password', [SetPasswordController::class, 'store'])->name('password.setup.store');

    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

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
