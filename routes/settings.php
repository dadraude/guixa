<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\EmailChangeController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

// Before the token route, so that "taken" is not read as a token.
Route::get('settings/email/taken', [EmailChangeController::class, 'taken'])->name('email-change.taken');
Route::get('settings/email/{token}', [EmailChangeController::class, 'show'])->name('email-change.show');
Route::post('settings/email/{token}', [EmailChangeController::class, 'confirm'])->name('email-change.confirm');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile')->name('settings');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/email', [EmailChangeController::class, 'cancel'])->name('email-change.cancel');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});
