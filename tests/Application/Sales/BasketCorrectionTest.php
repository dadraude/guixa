<?php

use Guixa\Sales\Application\CorrectBasket;
use Guixa\Sales\Application\StockCorrector;
use Guixa\Sales\Application\WalletLedger;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\Error\BasketCorrectionIdentityConflict;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Sales\Domain\Error\InvalidBasketCorrection;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;

it('records one amount correction for a validated basket when the identity is replayed (R23, R32)', function () {
    $baskets = new InMemoryBasketRepository;
    $when = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $basket = new Basket('basket-1', 'household-1', 'Household K', BasketState::Draft, 'manager', $when, [
        new BasketLine('line-1', 'basket-1', 'product-1', 'Oil', 'bottle', 1200, false, null, Quantity::fromString('1'), $when),
    ]);
    $baskets->save($basket);
    $baskets->save($basket->validated('manager', $when));
    $wallet = new class implements WalletLedger
    {
        public array $corrections = [];

        public function charge(Actor $actor, string $householdId, string $basketId, int $totalCents): void {}

        public function balance(Actor $actor, string $householdId): int
        {
            return 0;
        }

        public function correct(Actor $actor, string $householdId, string $basketId, int $amountCents, string $reason, string $correctionId): void
        {
            $this->corrections[$correctionId] = $amountCents;
        }
    };
    $correct = new CorrectBasket($baskets, $wallet, new class implements StockCorrector
    {
        public function correct(Actor $actor, string $productId, string $quantity, string $reason, string $basketId, string $correctionId): void {}
    }, new ImmediateTransactionRunner, new FrozenClock('2026-10-30 11:00:00'));

    $correct(Actor::manager('manager'), 'basket-1', 200, 'Price correction', 'correction-1');
    $correct(Actor::manager('manager'), 'basket-1', 200, 'Price correction', 'correction-1');

    expect($baskets->get('basket-1')->adjustments)->toHaveCount(1)
        ->and($wallet->corrections)->toHaveCount(1)
        ->and($baskets->get('basket-1')->validatedTotalCents)->toBe(1200);

    expect(fn () => $correct(Actor::manager('manager'), 'basket-1', 300, 'Changed', 'correction-1'))
        ->toThrow(BasketCorrectionIdentityConflict::class);
    expect(fn () => $correct(Actor::manager('other-manager'), 'basket-1', 200, 'Price correction', 'correction-1'))
        ->toThrow(BasketCorrectionIdentityConflict::class);
    expect(fn () => $correct(Actor::manager('manager'), 'missing-basket', 200, 'Reason', 'correction-2'))
        ->toThrow(BasketNotFound::class);
    expect(fn () => $correct(Actor::manager('manager'), 'basket-1', 0, 'Reason', 'correction-2'))
        ->toThrow(InvalidBasketCorrection::class);
    expect(fn () => $correct(Actor::manager('manager'), 'basket-1', 100, ' ', 'correction-2'))
        ->toThrow(InvalidBasketCorrection::class);
    expect(fn () => $correct(Actor::memberOf('member', 'household-1'), 'basket-1', 100, 'Reason', 'correction-2'))
        ->toThrow(AuthorizationException::class);
    expect($baskets->get('basket-1')->adjustments)->toHaveCount(1);
});
