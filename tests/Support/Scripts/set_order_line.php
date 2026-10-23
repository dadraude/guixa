<?php

/*
 * Runs `SetOrderLine` in its own process, so a test can run it next to another transaction.
 * Usage: php set_order_line.php <account id> <household id> <cycle id> <product id> <quantity>.
 * Prints `set` or the short name of the error thrown.
 */

use Guixa\Ordering\Application\SetOrderLine;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $app->make(SetOrderLine::class)(new Actor($argv[1], [Role::Member], $argv[2]), $argv[3], $argv[4], $argv[5]);
    echo 'set';
} catch (Throwable $e) {
    echo (new ReflectionClass($e))->getShortName();
}
