<?php

use Guixa\Shared\Domain\Error\InvalidLocalTime;
use Guixa\Shared\Domain\LocalTime;

function madrid(string $date, string $time): string
{
    $instant = LocalTime::at(new DateTimeImmutable($date), $time, new DateTimeZone('Europe/Madrid'));

    return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i');
}

it('resolves an ordinary local time using the winter and summer offsets', function () {
    expect(madrid('2026-01-14', '20:00'))->toBe('2026-01-14 19:00')
        ->and(madrid('2026-07-15', '20:00'))->toBe('2026-07-15 18:00');
});

it('moves a time that does not exist forward by the gap (spring forward, 2026-03-29)', function () {
    $instant = LocalTime::at(new DateTimeImmutable('2026-03-29'), '02:30', new DateTimeZone('Europe/Madrid'));

    expect($instant->format('H:i'))->toBe('03:30')
        ->and($instant->setTimezone(new DateTimeZone('UTC'))->format('H:i'))->toBe('01:30');
});

it('takes the first occurrence of a repeated time (fall back, 2026-10-25)', function () {
    // 02:30 happens at 00:30 UTC (CEST, +02:00) and again at 01:30 UTC (CET, +01:00).
    expect(madrid('2026-10-25', '02:30'))->toBe('2026-10-25 00:30');
});

it('ignores the time of day of the date it is given', function () {
    $instant = LocalTime::at(new DateTimeImmutable('2026-01-14 23:59:59'), '08:00', new DateTimeZone('Europe/Madrid'));

    expect($instant->format('Y-m-d H:i'))->toBe('2026-01-14 08:00');
});

it('resolves in the timezone it is given', function () {
    $instant = LocalTime::at(new DateTimeImmutable('2026-01-14'), '20:00', new DateTimeZone('America/New_York'));

    expect($instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'))->toBe('2026-01-15 01:00');
});

it('rejects a malformed time of day', function (string $time) {
    expect(fn () => LocalTime::at(new DateTimeImmutable('2026-01-14'), $time, new DateTimeZone('UTC')))
        ->toThrow(InvalidLocalTime::class);
})->with(['8:00', '24:00', '12:60', '12', '12:00:00', 'noon', "08:00\n"]);
