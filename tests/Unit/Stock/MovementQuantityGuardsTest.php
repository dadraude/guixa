<?php

use Guixa\Shared\Domain\Error\InvalidQuantity;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Domain\Error\InvalidMovementQuantity;
use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;

$when = new DateTimeImmutable('2026-11-01');

it('rejects stock movements that would move nothing', function () use ($when) {
    $zero = SignedQuantity::ofThousandths(0);

    expect(fn () => StockMovement::basketSale('m', 'p', Quantity::fromString('0'), $when, 'u', 'b'))
        ->toThrow(InvalidMovementQuantity::class, 'greater than zero')
        ->and(fn () => StockMovement::basketCorrection('m', 'p', $zero, $when, 'u', 'reason', 'b', 'c'))
        ->toThrow(InvalidMovementQuantity::class, 'cannot be zero');
});

it('rejects a signed quantity with two minus signs', function () {
    expect(fn () => SignedQuantity::fromString('--1'))->toThrow(InvalidQuantity::class)
        ->and(SignedQuantity::fromString('-1.500')->thousandths)->toBe(-1500);
});
