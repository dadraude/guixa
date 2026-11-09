<?php

/* Runs one membership command in another database session for deterministic locking tests. */

use Guixa\Membership\Application\AddMember;
use Guixa\Membership\Application\GrantStaffRole;
use Guixa\Membership\Application\MoveMember;
use Guixa\Shared\Application\Actor;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$manager = Actor::manager('00000000-0000-7000-8000-00000000000a');

try {
    if ($argv[1] === 'add') {
        echo $app->make(AddMember::class)($manager, $argv[2], 'New member', 'new@example.org');
    } elseif ($argv[1] === 'move') {
        $app->make(MoveMember::class)($manager, $argv[3], $argv[2]);
        echo 'moved';
    } else {
        // The role change log keeps the actor as a user, so a grant needs a real one (argv[4]).
        $app->make(GrantStaffRole::class)(Actor::manager($argv[4]), $argv[3], 'manager');
        echo 'granted';
    }
} catch (Throwable $error) {
    echo (new ReflectionClass($error))->getShortName();
}
