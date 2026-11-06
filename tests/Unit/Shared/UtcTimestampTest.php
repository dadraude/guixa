<?php

use Guixa\Shared\Domain\UtcTimestamp;

afterEach(fn () => date_default_timezone_set('UTC'));

it('stores an instant as UTC text without an offset, whatever offset it carried', function () {
    $madrid = new DateTimeImmutable('2026-11-05 00:30:00.123456', new DateTimeZone('Europe/Madrid'));

    expect(UtcTimestamp::toStorage($madrid))->toBe('2026-11-04 23:30:00.123456')
        ->and(UtcTimestamp::toStorage(null))->toBeNull();
});

it('reads stored text back as the same instant in UTC, independent of the process timezone', function () {
    date_default_timezone_set('Europe/Madrid');

    $read = UtcTimestamp::fromStorage('2026-11-04 23:30:00');

    expect($read->getTimestamp())->toBe((new DateTimeImmutable('2026-11-05 00:30:00', new DateTimeZone('Europe/Madrid')))->getTimestamp())
        ->and($read->getTimezone()->getName())->toBe('UTC')
        ->and(UtcTimestamp::fromStorage(null))->toBeNull();
});

it('keeps the cooperative-local day when an instant is read across midnight', function () {
    $read = UtcTimestamp::fromStorage('2026-11-04 23:30:00')->setTimezone(new DateTimeZone('Europe/Madrid'));

    expect($read->format('Y-m-d H:i'))->toBe('2026-11-05 00:30');
});
