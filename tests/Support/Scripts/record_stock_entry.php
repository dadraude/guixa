<?php

/* Runs the public stock-entry use case in another process for a lock race. */

use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\RecordStockEntry;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $app->make(RecordStockEntry::class)(Actor::manager('00000000-0000-7000-8000-0000000000a1'), $argv[1], '1', null);
    echo 'done';
} catch (Throwable $e) {
    echo (new ReflectionClass($e))->getShortName();
}
