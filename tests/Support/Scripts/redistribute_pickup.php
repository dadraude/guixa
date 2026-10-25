<?php

use Guixa\Ordering\Application\SetReceivedTotal;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $app->make(SetReceivedTotal::class)(new Actor($argv[1], [Role::Manager]), $argv[2], $argv[3], $argv[4], $argv[5], includeCompleted: true);
    echo 'redistributed';
} catch (Throwable $error) {
    echo (new ReflectionClass($error))->getShortName();
}
