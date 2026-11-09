<?php

use Guixa\Shared\Domain\Money;
use Guixa\Wallet\Domain\Error\InvalidChargeAmount;
use Guixa\Wallet\Domain\Movement;

$when = new DateTimeImmutable('2026-11-01');

it('rejects a basket charge or credit that is not greater than zero', function () use ($when) {
    expect(fn () => Movement::basketCharge('m', 'h', Money::ofCents(0), 'b', $when, 'u'))->toThrow(InvalidChargeAmount::class)
        ->and(fn () => Movement::basketCredit('m', 'h', Money::ofCents(-5), 'b', $when, 'u'))->toThrow(InvalidChargeAmount::class);
});
