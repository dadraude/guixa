<?php

use App\Http\Controllers\Manage\ReturnItemController;
use App\Http\Controllers\Manage\StockProductController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
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
});

require __DIR__.'/settings.php';
