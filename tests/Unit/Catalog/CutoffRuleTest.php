<?php

use Guixa\Catalog\Domain\Error\InvalidCutoffRule;
use Guixa\Catalog\Domain\ValueObject\CutoffRule;

function closesAtUtc(CutoffRule $rule, string $pickupDate, string $timezone): string
{
    $instant = $rule->closesAt(new DateTimeImmutable($pickupDate), new DateTimeZone($timezone));

    return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i');
}

it('counts days back from the pickup date and applies the local time (R5)', function () {
    $rule = CutoffRule::of(3, '20:00');

    // Saturday 7 November 2026, so Wednesday 4th at 20:00 Madrid (CET, +01:00).
    expect(closesAtUtc($rule, '2026-11-07', 'Europe/Madrid'))->toBe('2026-11-04 19:00');
});

it('keeps the local time in two timezones (R5)', function (string $timezone, string $expectedUtc) {
    expect(closesAtUtc(CutoffRule::of(3, '20:00'), '2026-11-07', $timezone))->toBe($expectedUtc);
})->with([
    'Europe/Madrid' => ['Europe/Madrid', '2026-11-04 19:00'],
    'America/Mexico_City' => ['America/Mexico_City', '2026-11-05 02:00'],
]);

it('ignores the time of day of the pickup date it is given', function () {
    expect(closesAtUtc(CutoffRule::of(1, '08:00'), '2026-11-07 23:59:59', 'Europe/Madrid'))->toBe('2026-11-06 07:00');
});

it('crosses a month boundary', function () {
    expect(closesAtUtc(CutoffRule::of(6, '12:00'), '2026-11-03', 'Europe/Madrid'))->toBe('2026-10-28 11:00');
});

it('moves a cutoff that falls in the skipped hour forward by the gap (S38, Madrid)', function () {
    // Clocks go forward on Sunday 29 March 2026 at 02:00; pickup is Monday 30th.
    $rule = CutoffRule::of(1, '02:30');

    expect($rule->closesAt(new DateTimeImmutable('2026-03-30'), new DateTimeZone('Europe/Madrid'))->format('Y-m-d H:i'))
        ->toBe('2026-03-29 03:30')
        ->and(closesAtUtc($rule, '2026-03-30', 'Europe/Madrid'))->toBe('2026-03-29 01:30');
});

it('takes the first occurrence of a repeated time (S38, Madrid fall back)', function () {
    // Clocks go back on Sunday 25 October 2026 at 03:00; pickup is Monday 26th, so 02:30 happens twice.
    expect(closesAtUtc(CutoffRule::of(1, '02:30'), '2026-10-26', 'Europe/Madrid'))->toBe('2026-10-25 00:30');
});

it('resolves the same rule in Mexico City across the weeks Madrid changes its clocks (S38)', function () {
    // Mexico City has no daylight saving: 02:30 always exists and the offset stays -06:00.
    $rule = CutoffRule::of(1, '02:30');

    expect(closesAtUtc($rule, '2026-03-30', 'America/Mexico_City'))->toBe('2026-03-29 08:30')
        ->and(closesAtUtc($rule, '2026-10-26', 'America/Mexico_City'))->toBe('2026-10-25 08:30');
});

it('accepts 1 to 6 days before and nothing else (R5)', function (int $days) {
    expect(fn () => CutoffRule::of($days, '20:00'))->toThrow(InvalidCutoffRule::class);
})->with([0, 7, -1]);

it('accepts every day count from 1 to 6', function (int $days) {
    expect(CutoffRule::of($days, '20:00')->daysBefore)->toBe($days);
})->with([1, 2, 3, 4, 5, 6]);

it('rejects a malformed time', function (string $time) {
    expect(fn () => CutoffRule::of(3, $time))->toThrow(InvalidCutoffRule::class);
})->with(['', '8:00', '24:00', '20:60', '20:00:00', 'noon']);

it('compares by value', function () {
    expect(CutoffRule::of(3, '20:00')->equals(CutoffRule::of(3, '20:00')))->toBeTrue()
        ->and(CutoffRule::of(3, '20:00')->equals(CutoffRule::of(2, '20:00')))->toBeFalse()
        ->and(CutoffRule::of(3, '20:00')->equals(CutoffRule::of(3, '20:30')))->toBeFalse();
});
