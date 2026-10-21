<?php

use Guixa\Shared\Infrastructure\SystemClock;
use Tests\Support\FrozenClock;

it('freezes time in any timezone', function () {
    $clock = new FrozenClock('2026-03-29 02:30', 'America/New_York');

    expect($clock->timezone()->getName())->toBe('America/New_York')
        ->and($clock->now()->format('Y-m-d H:i'))->toBe('2026-03-29 02:30')
        ->and($clock->now())->toEqual($clock->now());

    $clock->travelTo('2026-03-30 09:00');

    expect($clock->now()->format('Y-m-d H:i'))->toBe('2026-03-30 09:00');
});

it('tells the time in the cooperative timezone', function () {
    $clock = new SystemClock('Europe/Madrid');

    expect($clock->timezone()->getName())->toBe('Europe/Madrid')
        ->and($clock->now()->getTimezone()->getName())->toBe('Europe/Madrid');
});
