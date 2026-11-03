<?php

use Guixa\Sales\Application\BasketData;
use Guixa\Sales\Application\CorrectBasket;
use Guixa\Sales\Application\WalletLedger;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketAdjustment;
use Guixa\Sales\Domain\BasketAdjustmentLine;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketLineKind;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\Error\BasketCorrectionIdentityConflict;
use Guixa\Sales\Domain\Error\InvalidBasketCorrection;
use Guixa\Sales\Infrastructure\Stock\StockBasketCorrector;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\CorrectBasketStock;
use Guixa\Stock\Domain\Error\NotEnoughStock;
use Guixa\Stock\Domain\StockLedger;
use Guixa\Stock\Domain\StockMovement;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\InMemoryStockMovementRepository;
use Tests\Support\InMemory\InMemoryStockProductRepository;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;

function goodsCorrectionWorld(): object
{
    $when = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $products = new InMemoryStockProductRepository;
    $oil = $products->create('Oil', 'bottle', Money::ofCents(1200), false, null);
    $movements = new InMemoryStockMovementRepository;
    $movements->add(StockMovement::entry($movements->nextId(), $oil->id, Quantity::fromString('5'), $when, 'manager', null));
    $baskets = new InMemoryBasketRepository;
    $basket = new Basket('basket-1', 'household-1', 'Household K', BasketState::Draft, 'manager', $when, [
        new BasketLine('line-1', 'basket-1', $oil->id, 'Oil', 'bottle', 1200, false, null, Quantity::fromString('3'), $when),
    ]);
    $baskets->save($basket);
    $transactions = new ImmediateTransactionRunner;
    $clock = new FrozenClock('2026-10-30 11:00:00');
    $ledger = new StockLedger($movements);
    $sale = StockMovement::basketSale($movements->nextId(), $oil->id, Quantity::fromString('3'), $when, 'manager', 'basket-1');
    $movements->add($sale);
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
    $stock = new StockBasketCorrector(new CorrectBasketStock($products, $movements, $ledger, $transactions, $clock));

    return (object) [
        'oil' => $oil->id, 'baskets' => $baskets, 'movements' => $movements, 'wallet' => $wallet,
        'stockCorrector' => new CorrectBasketStock($products, $movements, $ledger, $transactions, $clock),
        'correct' => new CorrectBasket($baskets, $wallet, $stock, $transactions, $clock),
    ];
}

it('records returned goods with a corresponding amount as linked quantity, stock and wallet corrections (S86, R23, R27, R32)', function () {
    $world = goodsCorrectionWorld();
    $original = $world->baskets->get('basket-1');

    ($world->correct)(Actor::manager('manager'), 'basket-1', 1200, 'One bottle came back', 'correction-1', [$world->oil => '1']);

    $basket = $world->baskets->get('basket-1');
    expect($basket->adjustments)->toHaveCount(1)
        ->and($basket->adjustments[0]->lines)->toHaveCount(1)
        ->and($basket->adjustments[0]->lines[0]->productId)->toBe($world->oil)
        ->and($basket->adjustments[0]->lines[0]->quantityDeltaThousandths)->toBe(-1000)
        ->and($basket->effectiveQuantity($world->oil))->toBe(2000)
        ->and($basket->effectiveTotalCents())->toBe(3600 - 1200)
        ->and($basket->validatedTotalCents)->toBe(3600)
        ->and($basket->lines)->toEqual($original->lines)
        ->and($world->wallet->corrections)->toBe(['correction-1' => 1200])
        ->and($world->movements->available($world->oil)->thousandths)->toBe(3000);
});

it('uses effective quantities for partial and repeated corrections and rejects returning more than is left (R23, R32)', function () {
    $world = goodsCorrectionWorld();
    $manager = Actor::manager('manager');

    ($world->correct)($manager, 'basket-1', 1200, 'First bottle', 'correction-1', [$world->oil => '1']);
    ($world->correct)($manager, 'basket-1', 1200, 'Second bottle', 'correction-2', [$world->oil => '1']);
    expect(fn () => ($world->correct)($manager, 'basket-1', 2400, 'Too many', 'correction-3', [$world->oil => '2']))
        ->toThrow(InvalidBasketCorrection::class);

    $basket = $world->baskets->get('basket-1');
    $data = BasketData::from($basket);
    expect($basket->adjustments)->toHaveCount(2)
        ->and($basket->effectiveQuantity($world->oil))->toBe(1000)
        ->and($data->stockLines[0]->quantity)->toBe('3.000')
        ->and($data->stockLines[0]->effectiveQuantity)->toBe('1.000')
        ->and($data->stockLines[0]->effectiveAmountCents)->toBe(1200)
        ->and($data->effectiveTotalCents)->toBe(1200)
        ->and($data->pendingCollection)->toBeTrue()
        ->and($world->movements->available($world->oil)->thousandths)->toBe(4000);
});

it('leaves no effective goods pending collection after a full reversal (R24a, R23)', function () {
    $world = goodsCorrectionWorld();

    ($world->correct)(Actor::manager('manager'), 'basket-1', 3600, 'Everything came back', 'correction-1', [$world->oil => '3']);

    $data = BasketData::from($world->baskets->get('basket-1'));
    expect($data->stockLines[0]->effectiveQuantity)->toBe('0.000')
        ->and($data->stockLines[0]->quantity)->toBe('3.000')
        ->and($data->effectiveTotalCents)->toBe(0)
        ->and($data->state)->toBe('validated')
        ->and($data->pendingCollection)->toBeFalse()
        ->and($world->movements->available($world->oil)->thousandths)->toBe(5000);
});

it('rejects an invalid source, quantity or reason without any effect (R23, R32)', function (array $goods, string $reason) {
    $world = goodsCorrectionWorld();
    $movementsBefore = count($world->movements->forProduct($world->oil));

    expect(fn () => ($world->correct)(Actor::manager('manager'), 'basket-1', 1200, $reason, 'correction-1',
        array_combine(array_map(fn ($key) => $key === 'oil' ? $world->oil : $key, array_keys($goods)), $goods)))
        ->toThrow(InvalidBasketCorrection::class);

    expect($world->baskets->get('basket-1')->adjustments)->toBe([])
        ->and($world->wallet->corrections)->toBe([])
        ->and($world->movements->forProduct($world->oil))->toHaveCount($movementsBefore);
})->with([
    'product not in the basket' => [['other-product' => '1'], 'Reason'],
    'zero quantity' => [['oil' => '0'], 'Reason'],
    'negative quantity' => [['oil' => '-1'], 'Reason'],
    'malformed quantity' => [['oil' => 'one'], 'Reason'],
    'more than the basket holds' => [['oil' => '3.001'], 'Reason'],
    'blank reason' => [['oil' => '1'], '  '],
]);

it('replays a goods correction once and rejects changed contents under the same identity (R32)', function () {
    $world = goodsCorrectionWorld();
    $manager = Actor::manager('manager');

    ($world->correct)($manager, 'basket-1', 1200, 'Came back', 'correction-1', [$world->oil => '1']);
    ($world->correct)($manager, 'basket-1', 1200, 'Came back', 'correction-1', [$world->oil => '1']);
    expect(fn () => ($world->correct)($manager, 'basket-1', 1200, 'Came back', 'correction-1', [$world->oil => '2']))
        ->toThrow(BasketCorrectionIdentityConflict::class);
    expect(fn () => ($world->correct)($manager, 'basket-1', 1200, 'Came back', 'correction-1'))
        ->toThrow(BasketCorrectionIdentityConflict::class);

    expect($world->baskets->get('basket-1')->adjustments)->toHaveCount(1)
        ->and($world->movements->available($world->oil)->thousandths)->toBe(3000)
        ->and($world->wallet->corrections)->toBe(['correction-1' => 1200]);
});

it('never restores stock or changes quantities for an amount-only correction (R23, R32)', function () {
    $world = goodsCorrectionWorld();

    ($world->correct)(Actor::manager('manager'), 'basket-1', 500, 'Price error', 'correction-1');

    $basket = $world->baskets->get('basket-1');
    expect($basket->adjustments[0]->lines)->toBe([])
        ->and($basket->effectiveQuantity($world->oil))->toBe(3000)
        ->and($basket->effectiveTotalCents())->toBe(3100)
        ->and($world->movements->available($world->oil)->thousandths)->toBe(2000)
        ->and(BasketData::from($basket)->pendingCollection)->toBeTrue();
});

it('lets Finance record a goods correction but refuses a member (R23)', function () {
    $world = goodsCorrectionWorld();

    ($world->correct)(new Actor('finance', [Role::Finance]), 'basket-1', 1200, 'Came back', 'correction-1', [$world->oil => '1']);

    expect($world->baskets->get('basket-1')->adjustments)->toHaveCount(1);
    expect(fn () => ($world->correct)(Actor::memberOf('member', 'household-1'), 'basket-1', 1200, 'Came back', 'correction-2', [$world->oil => '1']))
        ->toThrow(AuthorizationException::class);
});

it('refuses a signed stock correction that would make stock negative (R27, R28)', function () {
    $world = goodsCorrectionWorld();

    expect(fn () => ($world->stockCorrector)(Actor::manager('manager'), $world->oil, '-2.001', 'Extra bottles taken', 'basket-1', 'correction-9'))
        ->toThrow(NotEnoughStock::class);

    expect($world->movements->available($world->oil)->thousandths)->toBe(2000);
    ($world->stockCorrector)(Actor::manager('manager'), $world->oil, '-2', 'Extra bottles taken', 'basket-1', 'correction-9');
    expect($world->movements->available($world->oil)->thousandths)->toBe(0);
});

it('replays a full reversal and a partial return as a no-op although the quantity no longer remains (R32)', function (string $returned, string $remaining) {
    $world = goodsCorrectionWorld();
    $manager = Actor::manager('manager');
    $goods = [$world->oil => $returned];

    ($world->correct)($manager, 'basket-1', 1200, 'Came back', 'correction-1', $goods);
    ($world->correct)($manager, 'basket-1', 1200, 'Came back', 'correction-1', $goods);
    ($world->correct)($manager, 'basket-1', 1200, '  Came back ', 'correction-1', $goods);

    expect($world->baskets->get('basket-1')->adjustments)->toHaveCount(1)
        ->and($world->baskets->get('basket-1')->effectiveQuantity($world->oil))->toBe((int) $remaining)
        ->and($world->wallet->corrections)->toBe(['correction-1' => 1200])
        ->and($world->movements->forProduct($world->oil))->toHaveCount(3);
    expect(fn () => ($world->correct)($manager, 'basket-1', 1200, 'Came back', 'correction-1', [$world->oil => '1']))
        ->toThrow(BasketCorrectionIdentityConflict::class);
    expect(fn () => ($world->correct)($manager, 'basket-1', 1300, 'Came back', 'correction-1', $goods))
        ->toThrow(BasketCorrectionIdentityConflict::class);
})->with([
    'full reversal' => ['3', '0'],
    'two of three' => ['2', '1000'],
]);

it('keeps a basket with an active return line pending after every stock line is reversed (R24a, S88)', function () {
    $when = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $basket = new Basket('basket-2', 'household-1', 'Household K', BasketState::Draft, 'manager', $when, [
        new BasketLine('line-1', 'basket-2', 'oil', 'Oil', 'bottle', 1200, false, null, Quantity::fromString('1'), $when),
        new BasketLine('line-2', 'basket-2', null, 'Jar', 'jar', 50, false, null, Quantity::fromString('2'), $when, kind: BasketLineKind::Return, returnItemId: 'jar'),
    ]);
    $validated = $basket->validated('manager', $when)->withAdjustment(new BasketAdjustment(
        'correction-1', 'basket-2', 1200, 'Came back', 'manager', $when, [new BasketAdjustmentLine('oil', -1000)]));

    expect($validated->effectiveQuantity('oil'))->toBe(0)
        ->and($validated->goodsFullyReversed())->toBeFalse()
        ->and(BasketData::from($validated)->pendingCollection)->toBeTrue();
});
