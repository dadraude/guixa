<?php

use Guixa\Shared\Domain\Error\InvalidQuantity;
use Guixa\Shared\Domain\Quantity;

it('maps strings to thousandths and back', function (string $text, int $thousandths, string $canonical) {
    $quantity = Quantity::fromString($text);

    expect($quantity->thousandths)->toBe($thousandths)
        ->and($quantity->toString())->toBe($canonical)
        ->and(Quantity::ofThousandths($thousandths)->toString())->toBe($canonical);
})->with([
    ['1.125', 1125, '1.125'],
    ['1.12', 1120, '1.120'],
    ['1.1', 1100, '1.100'],
    ['2', 2000, '2.000'],
    ['0', 0, '0.000'],
    ['0.001', 1, '0.001'],
    ['1234.5', 1234500, '1234.500'],
]);

it('rejects a negative quantity', function () {
    expect(fn () => Quantity::fromString('-1.000'))->toThrow(InvalidQuantity::class)
        ->and(fn () => Quantity::ofThousandths(-1))->toThrow(InvalidQuantity::class);
});

it('rejects text that is not a quantity with at most 3 decimals', function (string $text) {
    expect(fn () => Quantity::fromString($text))->toThrow(InvalidQuantity::class);
})->with(['', 'abc', '1.1234', '1,5', '.5', '1.', ' 1', '1e3', "1.5\n", '12345678', '99999999999999999999']);

it('tells zero and whole quantities apart', function () {
    expect(Quantity::fromString('0')->isZero())->toBeTrue()
        ->and(Quantity::fromString('0.001')->isZero())->toBeFalse()
        ->and(Quantity::fromString('2.000')->isWhole())->toBeTrue()
        ->and(Quantity::fromString('2.001')->isWhole())->toBeFalse();
});

it('rounds up to a whole quantity', function (string $text, string $expected) {
    expect(Quantity::fromString($text)->ceilToWhole()->toString())->toBe($expected);
})->with([
    ['0.000', '0.000'],
    ['0.001', '1.000'],
    ['1.000', '1.000'],
    ['1.001', '2.000'],
    ['2.999', '3.000'],
]);

it('compares by value', function () {
    expect(Quantity::fromString('1.5')->equals(Quantity::ofThousandths(1500)))->toBeTrue()
        ->and(Quantity::fromString('1.5')->equals(Quantity::fromString('1.501')))->toBeFalse();
});

it('rounds the containers a quantity needs up (R18a)', function (string $quantity, string $rate, int $expected) {
    expect(Quantity::fromString($quantity)->containersAt(Quantity::fromString($rate)))->toBe($expected);
})->with([
    ['1.300', '2', 3],
    ['0.500', '2', 1],
    ['3', '1', 3],
    ['0.001', '0.001', 1],
    ['0', '5', 0],
    ['1', '0', 0],
    ['9999999.999', '9999999.999', 99999999980001],
]);
