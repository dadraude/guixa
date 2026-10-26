<?php

/*
 * Runs `RemoveManagerRole` in its own process, so a test can run it next to another transaction.
 * Usage: php remove_manager_role.php <account id>. Prints `removed` or the short name of the error thrown.
 */

use Guixa\Membership\Application\RemoveManagerRole;
use Guixa\Shared\Application\Actor;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $app->make(RemoveManagerRole::class)(Actor::manager('00000000-0000-7000-8000-00000000000a'), $argv[1]);
    echo 'removed';
} catch (Throwable $e) {
    echo (new ReflectionClass($e))->getShortName();
}
