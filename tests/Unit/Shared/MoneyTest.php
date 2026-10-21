<?php

use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;

it('multiplies by a quantity, rounding half up to the cent', function (int $cents, string $quantity, int $expected) {
    $result = Money::ofCents($cents)->multiply(Quantity::fromString($quantity));

    expect($result->cents)->toBe($expected);
})->with([
    '1.120 x 20.00 is exact' => [2000, '1.120', 2240],
    'half rounds up' => [1, '0.500', 1],
    'just below half rounds down' => [1, '0.499', 0],
    '2.5 cents rounds up to 3' => [5, '0.500', 3],
    'whole quantity' => [350, '3', 1050],
    'zero quantity' => [350, '0', 0],
]);

it('rounds a credit by magnitude, so it mirrors the charge', function () {
    $quantity = Quantity::fromString('0.500');

    expect(Money::ofCents(-5)->multiply($quantity)->cents)->toBe(-3)
        ->and(Money::ofCents(5)->multiply($quantity)->negate()->cents)->toBe(-3);
});

it('adds, subtracts and negates', function () {
    $ten = Money::ofCents(1000);
    $three = Money::ofCents(300);

    expect($ten->add($three)->cents)->toBe(1300)
        ->and($three->subtract($ten)->cents)->toBe(-700)
        ->and($three->subtract($ten)->isNegative())->toBeTrue()
        ->and($ten->negate()->cents)->toBe(-1000)
        ->and($ten->equals(Money::ofCents(1000)))->toBeTrue()
        ->and($ten->equals($three))->toBeFalse();
});
