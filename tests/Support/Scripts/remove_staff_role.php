<?php

/*
 * Runs `RemoveStaffRole` in its own process, so a test can run it next to another transaction.
 * Usage: php remove_staff_role.php <actor id> <account id> <role>. Prints `removed` or the short name of the error thrown.
 */

use Guixa\Membership\Application\RemoveStaffRole;
use Guixa\Shared\Application\Actor;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $app->make(RemoveStaffRole::class)(Actor::manager($argv[1]), $argv[2], $argv[3]);
    echo 'removed';
} catch (Throwable $e) {
    echo (new ReflectionClass($e))->getShortName();
}
