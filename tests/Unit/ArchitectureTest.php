<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

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

// Providers are the composition root and legitimately use the container (Gate definitions,
// Fortify callbacks). Everywhere else dependencies are injected, not located (DRA-71 review).
arch('app code injects its dependencies instead of locating them with app() or resolve()')
    ->expect('App')
    ->not->toUse(['app', 'resolve', 'Illuminate\Support\Facades\App', 'Illuminate\Container\Container'])
    ->ignoring('App\Providers');
