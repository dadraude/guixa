<?php

use Guixa\Ordering\Application\SendEmptyProducerOrders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A producer that closes with no line to send is sent without anyone asking (D10, R12a).
Schedule::call(fn () => app(SendEmptyProducerOrders::class)())
    ->name('send-empty-producer-orders')
    ->everyMinute()
    ->withoutOverlapping();
