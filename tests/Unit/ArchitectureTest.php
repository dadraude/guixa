<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/*
 * Project rules that a reviewer would otherwise have to check by eye.
 * When a review finding keeps coming back, it belongs here.
 */

arch('no debugging leftovers or unsafe PHP functions')
    ->preset()->php();

arch('no insecure functions')
    ->preset()->security();

arch('every file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('models use UUIDv7 primary keys (ADR 0005)')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait(HasUuids::class);

arch('actions expose a handle method')
    ->expect('App\Actions')
    ->classes()
    ->toHaveMethod('handle');

arch('domain code does not depend on the HTTP layer')
    ->expect(['App\Actions', 'App\Auth', 'App\Models', 'App\Mail'])
    ->not->toUse(['App\Http', 'Illuminate\Http']);

arch('domain code does not depend on console, providers or Inertia (DDD)')
    ->expect(['App\Actions', 'App\Auth', 'App\Models'])
    ->not->toUse(['App\Console', 'App\Providers', 'Inertia']);

arch('entities do not depend on use cases or mail (DDD)')
    ->expect('App\Models')
    ->not->toUse(['App\Actions', 'App\Mail']);

arch('mail does not depend on use cases, so there are no layer cycles (DDD)')
    ->expect('App\Mail')
    ->not->toUse('App\Actions');

arch('classes stay small enough to have one responsibility (SRP)')
    ->expect(['App\Actions', 'App\Auth', 'App\Models', 'App\Mail'])
    ->classes()
    ->toHaveLineCountLessThan(150);

arch('the commission_member pivot is only read by CommissionMemberships')
    ->expect('App\\Auth\\CommissionMemberships')
    ->toOnlyBeUsedIn('App\\Auth\\MemberRoles');

arch('mails are queued after the transaction commits')
    ->expect('App\Mail')
    ->toImplement(ShouldQueueAfterCommit::class);

arch('env() is only read from config files')
    ->expect('env')
    ->not->toBeUsedIn(['App', 'Database']);

// Pest cannot match method names, so this scans the source: unused magic links are
// revoked through MagicLinkBroker::revokeUnused, never by querying the relation (DRA-60 review).
test('actions do not query magic links directly', function () {
    $files = (new Finder)->files()->in(dirname(__DIR__, 2).'/app/Actions')->name('*.php');

    $offenders = [];
    foreach ($files as $file) {
        if (Str::contains($file->getContents(), 'magicLinks(')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

// Providers are the composition root and legitimately use the container (Gate definitions,
// Fortify callbacks). Everywhere else dependencies are injected, not located (DRA-71 review).
arch('app code injects its dependencies instead of locating them with app() or resolve()')
    ->expect('App')
    ->not->toUse(['app', 'resolve', 'Illuminate\Support\Facades\App', 'Illuminate\Container\Container'])
    ->ignoring('App\Providers');

// Pest cannot match method names, so the HTTP layer is guarded by two checks:
// 1. the arch rule rejects the classes a transaction starts from (DB facade and its
//    global alias, the connection and the database manager);
// 2. the source scan below rejects the calls that need no such class, such as
//    Member::query()->lockForUpdate(), $model->getConnection()->transaction() or the
//    global alias \DB::transaction().
arch('transactions do not start from the HTTP layer')
    ->expect('App\Http')
    ->not->toUse([
        DB::class,
        ConnectionInterface::class,
        DatabaseManager::class,
    ]);

test('the HTTP layer holds no transactions or locks, they live in actions', function () {
    $forbidden = ['lockForUpdate', 'sharedLock', '->transaction(', '::transaction('];

    $files = (new Finder)->files()->in(dirname(__DIR__, 2).'/app/Http')->name('*.php');

    $offenders = [];
    foreach ($files as $file) {
        if (Str::contains($file->getContents(), $forbidden)) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});
