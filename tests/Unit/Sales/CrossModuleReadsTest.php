<?php

use Guixa\Sales\Application\ReadWeeklyBasketStates;
use Guixa\Sales\Application\WeeklyBasketsOfCycle;
use Guixa\Sales\Application\WeeklySourceOrders;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketAdjustment;
use Guixa\Sales\Domain\BasketAdjustmentLine;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Infrastructure\Stock\StockModuleReturnItems;
use Guixa\Shared\Domain\Money;
use Guixa\Stock\Application\GetReturnItem;
use Guixa\Stock\Domain\ReturnItem;
use Tests\Support\InMemory\InMemoryReturnItemRepository;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;

it('reads a return item as Sales sees it and answers nothing for an unknown one', function () {
    $items = new InMemoryReturnItemRepository;
    $items->add(ReturnItem::create('r1', 'Crate', Money::ofCents(250)));
    $adapter = new StockModuleReturnItems(new GetReturnItem($items));

    $snapshot = $adapter->get('r1');

    expect($snapshot->name)->toBe('Crate')
        ->and($snapshot->amountCents)->toBe(250)
        ->and($snapshot->active)->toBeTrue()
        ->and($adapter->get('missing'))->toBeNull();
});

it('counts a product only corrected by an adjustment in the weekly basket quantities', function () {
    $when = new DateTimeImmutable('2026-11-01');
    $adjustment = new BasketAdjustment('a', 'basket', 0, 'late arrival', 'manager', $when, [new BasketAdjustmentLine('tomato', 2000)]);
    $baskets = new InMemoryBasketRepository;
    $baskets->save(new Basket('basket', 'household', 'Household K', BasketState::Validated, 'manager', $when,
        adjustments: [$adjustment], kind: BasketKind::Weekly, sourceOrderId: 'order'));
    $sourceOrders = new class implements WeeklySourceOrders
    {
        public function cyclesOf(array $sourceOrderIds): array
        {
            return array_fill_keys($sourceOrderIds, 'cycle');
        }

        public function ofCycle(string $cycleId): array
        {
            return ['order'];
        }
    };

    $states = (new ReadWeeklyBasketStates(new WeeklyBasketsOfCycle($baskets, $sourceOrders)))('cycle');

    expect($states)->toHaveCount(1)
        ->and($states[0]->householdId)->toBe('household')
        ->and($states[0]->effective)->toBe(['tomato' => 2000]);
});
