<?php

use Guixa\Sales\Application\ValidateBasket;
use Guixa\Shared\Application\Actor;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $app->make(ValidateBasket::class)(Actor::manager($argv[1]), $argv[2]);
    echo 'validated';
} catch (Throwable $error) {
    echo (new ReflectionClass($error))->getShortName();
}
