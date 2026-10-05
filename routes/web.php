<?php

use App\Auth\ActiveProfile;
use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\Auth\ActiveProfileController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Commissions\CommissionController;
use App\Http\Controllers\Commissions\CommissionMemberController;
use App\Http\Controllers\Commissions\CommissionRoleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Directory\DirectoryController;
use App\Http\Controllers\Households\HouseholdController;
use App\Http\Controllers\Households\InvitationController;
use App\Http\Controllers\Households\PendingInvitationController;
use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\Members\MemberController;
use App\Http\Controllers\Members\MemberHouseholdController;
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

Route::prefix('invitations')->name('invitations.')->group(function () {
    Route::get('{token}', [AcceptInvitationController::class, 'show'])->name('show');
    Route::post('{token}', [AcceptInvitationController::class, 'accept'])->name('accept');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/', function (Request $request, ActiveProfile $activeProfile) {
        return $activeProfile->isActingAsAdministrator($request->user())
            ? to_route('admin.home')
            : to_route('dashboard');
    })->name('home');

    Route::get('set-password', [SetPasswordController::class, 'create'])->name('password.setup');
    Route::post('set-password', [SetPasswordController::class, 'store'])->name('password.setup.store');

    Route::get('active-profile', [ActiveProfileController::class, 'edit'])->name('active-profile.edit');
    Route::post('active-profile', [ActiveProfileController::class, 'update'])->name('active-profile.update');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('can:manage-membership')->prefix('households')->name('households.')->group(function () {
        Route::get('/', [HouseholdController::class, 'index'])->name('index');
        Route::post('/', [HouseholdController::class, 'store'])->name('store');
        Route::patch('{household}', [HouseholdController::class, 'update'])->name('update');
        Route::post('{household}/invitations', [InvitationController::class, 'store'])->name('invitations.store');

        Route::prefix('invitations')->name('invitations.')->group(function () {
            Route::get('/', [PendingInvitationController::class, 'index'])->name('index');
            Route::post('{invitation}/resend', [PendingInvitationController::class, 'resend'])->name('resend');
            Route::delete('{invitation}', [PendingInvitationController::class, 'cancel'])->name('cancel');
        });
    });

    Route::middleware('can:manage-membership')->prefix('members')->name('members.')->group(function () {
        Route::get('/', [MemberController::class, 'index'])->name('index');
        Route::patch('{member}', [MemberController::class, 'update'])->name('update');
        Route::patch('{member}/household', [MemberHouseholdController::class, 'update'])->name('household.update');
    });

    Route::middleware('can:view-directory')->get('directory', DirectoryController::class)->name('directory.index');

    Route::middleware('can:view-commissions')->get('commissions', [CommissionController::class, 'index'])->name('commissions.index');

    Route::middleware('can:manage-membership')->prefix('commissions')->name('commissions.')->group(function () {
        Route::post('/', [CommissionController::class, 'store'])->name('store');
        Route::patch('{commission}', [CommissionController::class, 'update'])->name('update');
        Route::delete('{commission}', [CommissionController::class, 'destroy'])->name('destroy');
        Route::post('{commission}/members', [CommissionMemberController::class, 'store'])->name('members.store');
        Route::delete('{commission}/members/{member}', [CommissionMemberController::class, 'destroy'])->name('members.destroy');
    });

    Route::middleware('can:assign-roles')->prefix('commissions')->name('commissions.')->group(function () {
        Route::post('{commission}/roles', [CommissionRoleController::class, 'store'])->name('roles.store');
        Route::delete('{commission}/roles/{role}', [CommissionRoleController::class, 'destroy'])->name('roles.destroy');
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
