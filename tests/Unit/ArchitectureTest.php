<?php

declare(strict_types=1);

use App\Actions\InvitationUnavailable;
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
    ->toHaveMethod('handle')
    ->ignoring(InvitationUnavailable::class);

arch('domain code does not depend on the HTTP layer')
    ->expect(['App\Actions', 'App\Auth', 'App\Models', 'App\Mail'])
    ->not->toUse('App\Http');

arch('mails are queued after the transaction commits')
    ->expect('App\Mail')
    ->toImplement(ShouldQueueAfterCommit::class);

arch('env() is only read from config files')
    ->expect('env')
    ->not->toBeUsedIn(['App', 'Database']);
