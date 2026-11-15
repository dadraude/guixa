<?php

use Guixa\Sales\Application\CorrectBasketCollection;
use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\Error\BasketCollectionCorrectionIdentityConflict;
use Guixa\Sales\Domain\Error\InvalidBasketCollectionCorrection;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;

it('corrects collection without replacing original actor or time (R23b, R24a)', function () {
    $baskets = new InMemoryBasketRepository;
    $when = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $basket = new Basket('basket-1', 'household-1', 'Household K', BasketState::Draft, 'manager', $when, [
        new BasketLine('line-1', 'basket-1', 'product-1', 'Oil', 'bottle', 1200, false, null, Quantity::fromString('1'), $when),
    ]);
    $baskets->save($basket);
    $baskets->save($basket->validated('manager', $when)->collected('manager', $when));
    $correct = new CorrectBasketCollection($baskets, new ImmediateTransactionRunner, new FrozenClock('2026-10-30 11:00:00'));

    $correct(Actor::manager('other-manager'), 'basket-1', 'Marked by mistake', 'correction-1');

    $result = $baskets->get('basket-1');
    expect($result->state)->toBe(BasketState::Validated)
        ->and($result->collectedBy)->toBe('manager')
        ->and($result->collectedAt)->toEqual($when)
        ->and($result->collectionEvents)->toHaveCount(2)
        ->and($result->collectionEvents[0]->type)->toBe('collected')
        ->and($result->collectionEvents[1]->reason)->toBe('Marked by mistake');

    $correct(Actor::manager('other-manager'), 'basket-1', ' Marked by mistake ', 'correction-1');
    expect($baskets->get('basket-1')->collectionEvents)->toHaveCount(2);
    expect(fn () => $correct(Actor::manager('other-manager'), 'basket-1', 'Changed', 'correction-1'))
        ->toThrow(BasketCollectionCorrectionIdentityConflict::class);
    expect(fn () => $correct(Actor::manager('third-manager'), 'basket-1', 'Marked by mistake', 'correction-1'))
        ->toThrow(BasketCollectionCorrectionIdentityConflict::class);
    expect(fn () => $correct(Actor::manager('other-manager'), 'basket-1', ' ', 'correction-2'))
        ->toThrow(InvalidBasketCollectionCorrection::class);
    expect(fn () => $correct(Actor::memberOf('member', 'household-1'), 'basket-1', 'Mistake', 'correction-2'))
        ->toThrow(AuthorizationException::class);

    $collect = new MarkBasketCollected($baskets, new ImmediateTransactionRunner, new FrozenClock('2026-10-30 12:00:00'));
    $collect(Actor::manager('third-manager'), 'basket-1');
    $collect(Actor::manager('third-manager'), 'basket-1');
    $recollected = $baskets->get('basket-1');
    expect($recollected->state)->toBe(BasketState::Collected)
        ->and($recollected->collectedBy)->toBe('manager')
        ->and($recollected->collectedAt)->toEqual($when)
        ->and($recollected->collectionEvents)->toHaveCount(3)
        ->and($recollected->collectionEvents[2]->recordedBy)->toBe('third-manager');
    $correct(Actor::manager('other-manager'), 'basket-1', 'Marked by mistake', 'correction-1');
    expect($baskets->get('basket-1')->state)->toBe(BasketState::Collected);
    $correct(Actor::manager('other-manager'), 'basket-1', 'Again', 'correction-2');
    expect($baskets->get('basket-1')->state)->toBe(BasketState::Validated)
        ->and($baskets->get('basket-1')->collectionEvents)->toHaveCount(4);
});
