<?php

/*
 * Runs `CompletePickup` in its own process, so a test can run it next to another transaction.
 * Usage: php complete_pickup.php <manager account id> <cycle id> <household id>.
 * Prints `completed` or the short name of the error thrown.
 */

use Guixa\Ordering\Application\CompletePickup;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $app->make(CompletePickup::class)(new Actor($argv[1], [Role::Manager]), $argv[2], $argv[3]);
    echo 'completed';
} catch (Throwable $e) {
    echo (new ReflectionClass($e))->getShortName();
}
