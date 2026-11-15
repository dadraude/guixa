<?php

/*
 * Runs `SetStockAvailable` in its own process, so a test can run it next to another transaction.
 * Usage: php set_stock_available.php <manager account id> <product id> <available>.
 * Prints `done` or the short name of the error thrown.
 */

use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\SetStockAvailable;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $app->make(SetStockAvailable::class)(Actor::manager($argv[1]), $argv[2], $argv[3], 'inventory count');
    echo 'done';
} catch (Throwable $e) {
    echo (new ReflectionClass($e))->getShortName();
}
