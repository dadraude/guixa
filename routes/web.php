<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\SetPasswordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('magic-link/{token}', [MagicLinkController::class, 'show'])
    ->middleware('guest')
    ->name('magic-link.show');

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
    });
});

require __DIR__.'/settings.php';
