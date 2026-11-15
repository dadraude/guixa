<?php

use Guixa\Sales\Application\GetBasket;
use Guixa\Sales\Application\GetMemberBasket;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketAdjustment;
use Guixa\Sales\Domain\BasketCollectionEvent;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketState;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Tests\Support\InMemory\Sales\FakeAccountNames;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;

beforeEach(function () {
    $when = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $this->baskets = new InMemoryBasketRepository;
    $this->names = new FakeAccountNames;
    $basket = new Basket('basket-1', 'household-1', 'Household K', BasketState::Draft, 'manager', $when, [
        new BasketLine('line-1', 'basket-1', 'product-1', 'Oil', 'bottle', 1200, false, null, Quantity::fromString('1'), $when),
    ]);
    $basket = $basket->validated('manager', $when)->collected('manager', $when, 'event-1')
        ->withAdjustment(new BasketAdjustment('adjustment-1', 'basket-1', -200, 'Spilled', 'adjuster', $when))
        ->withAdjustment(new BasketAdjustment('adjustment-2', 'basket-1', 50, 'Counted again', 'adjuster', $when))
        ->collectionCorrected(new BasketCollectionEvent('event-2', 'basket-1', 'corrected', 'unknown-account', $when, 'Marked by mistake'));
    $this->baskets->save($basket);
    $this->names->know('manager', 'Maria Manager');
    $this->names->know('adjuster', 'Adela Adjuster');
});

it('names the author of each adjustment and collection event, corrections included, in one batch (R23a)', function () {
    $basket = (new GetBasket($this->baskets, $this->names))(Actor::manager('manager'), 'basket-1');

    expect(array_map(fn ($a) => $a->authorName, $basket->adjustments))->toBe(['Adela Adjuster', 'Adela Adjuster'])
        ->and(array_map(fn ($e) => $e->authorName, $basket->collectionEvents))->toBe(['Maria Manager', ''])
        ->and($this->names->lookups)->toHaveCount(1)
        ->and($this->names->lookups[0])->toHaveCount(3);
});

it('names the authors on the member basket too (R23a)', function () {
    $basket = (new GetMemberBasket($this->baskets, $this->names))(Actor::memberOf('member', 'household-1'), 'basket-1');

    expect($basket->adjustments[0]->authorName)->toBe('Adela Adjuster')
        ->and($basket->collectionEvents[0]->authorName)->toBe('Maria Manager')
        ->and($this->names->lookups)->toHaveCount(1);
});
