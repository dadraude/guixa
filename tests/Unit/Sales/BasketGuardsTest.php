<?php

use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketAdjustment;
use Guixa\Sales\Domain\BasketCollectionEvent;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\ContainerBalance;
use Guixa\Sales\Domain\Error\InvalidBasketCollectionCorrection;
use Guixa\Shared\Domain\Quantity;

$domainBasket = function (BasketState $state = BasketState::Draft, BasketKind $kind = BasketKind::Stock, bool $withLine = true): Basket {
    $when = new DateTimeImmutable('2026-11-01');
    $lines = $withLine
        ? [new BasketLine('line', 'basket', 'oil', 'Oil', 'bottle', 1200, false, null, Quantity::fromString('1'), $when)]
        : [];

    return new Basket('basket', 'household', 'Household K', $state, 'manager', $when, $lines, kind: $kind);
};

$when = new DateTimeImmutable('2026-11-01');

it('refuses to change, correct or validate a basket in the wrong state', function () use ($when, $domainBasket) {
    expect(fn () => $domainBasket(BasketState::Validated)->draft())->toThrow(DomainException::class, 'Only draft baskets can be changed.')
        ->and(fn () => $domainBasket(BasketState::Draft)->withAdjustment(new BasketAdjustment('a', 'basket', 100, 'why', 'manager', $when)))
        ->toThrow(DomainException::class, 'Only a validated basket can be corrected.')
        ->and(fn () => $domainBasket(withLine: false)->validated('manager', $when))
        ->toThrow(DomainException::class, 'A basket needs contents before validation.')
        ->and(fn () => $domainBasket()->collected('manager', $when))
        ->toThrow(DomainException::class, 'Only validated baskets can be collected.');
});

it('refuses a collection correction on a basket that was not collected', function () use ($when, $domainBasket) {
    $event = new BasketCollectionEvent('event', 'basket', 'corrected', 'manager', $when, 'typo');

    expect(fn () => $domainBasket(BasketState::Validated)->collectionCorrected($event))->toThrow(InvalidBasketCollectionCorrection::class);
});

it('refuses an actual quantity for a product the basket has no weekly line of', function () use ($domainBasket) {
    expect(fn () => $domainBasket(kind: BasketKind::Weekly)->withActualQuantity('unknown', Quantity::fromString('1')))
        ->toThrow(DomainException::class, 'The basket has no weekly line of that product.');
});

it('does not follow a source for a stock basket and refuses late lines for it', function () use ($when, $domainBasket) {
    $basket = $domainBasket();

    expect($basket->synchronizedWith([], $when))->toBe($basket)
        ->and(fn () => $basket->withLateLines([]))->toThrow(DomainException::class, 'Late lines can only join an unvalidated weekly basket.');
});

it('counts no containers for a basket that is not settled', function () use ($domainBasket) {
    expect(ContainerBalance::of([$domainBasket(BasketState::Draft)]))->toBe(0);
});
