<?php

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\InvalidQuantity;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\GetReturnItem;
use Guixa\Stock\Application\GetStockOffer;
use Guixa\Stock\Application\ListStock;
use Guixa\Stock\Application\RecordStockAdjustment;
use Guixa\Stock\Application\RecordStockEntry;
use Guixa\Stock\Application\SellStock;
use Guixa\Stock\Domain\Error\AdjustmentNoteRequired;
use Guixa\Stock\Domain\Error\InvalidMovementQuantity;
use Guixa\Stock\Domain\Error\NotEnoughStock;
use Guixa\Stock\Domain\Error\ReturnItemNotFound;
use Guixa\Stock\Domain\Error\StockProductNotFound;
use Guixa\Stock\Domain\MovementType;
use Guixa\Stock\Domain\ReturnItem;
use Guixa\Stock\Domain\StockLedger;
use Guixa\Stock\Domain\StockProduct;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\InMemoryReturnItemRepository;
use Tests\Support\InMemory\InMemoryStockMovementRepository;
use Tests\Support\InMemory\InMemoryStockProductRepository;

beforeEach(function () {
    $this->products = new InMemoryStockProductRepository;
    $this->movements = new InMemoryStockMovementRepository;
    $this->returnItems = new InMemoryReturnItemRepository;
    $this->clock = new FrozenClock('2026-10-22 10:00:00');
    $ledger = new StockLedger($this->movements);
    $transactions = new ImmediateTransactionRunner;
    $this->manager = Actor::manager('manager-account');

    $this->entry = new RecordStockEntry($this->products, $this->movements, $ledger, $transactions, $this->clock);
    $this->adjust = new RecordStockAdjustment($this->products, $this->movements, $ledger, $transactions, $this->clock);
    $this->sell = new SellStock($this->products, $this->movements, $ledger, $transactions, $this->clock);
    $this->listStock = new ListStock($this->products, $this->movements);
    $this->offer = new GetStockOffer($this->products, $this->movements);
    $this->returnItem = new GetReturnItem($this->returnItems);

    $honey = StockProduct::create($this->products->nextId(), 'Honey', 'jar', Money::ofCents(650), false, '2.000');
    $this->products->add($honey);
    $this->honeyId = $honey->id;

    $this->available = fn (?string $id = null) => collect(($this->listStock)($this->manager))
        ->firstWhere('id', $id ?? $this->honeyId)->available;
});

it('adds an entry to the available stock (R26)', function () {
    ($this->entry)($this->manager, $this->honeyId, '10', 'bought from X');

    expect(($this->available)())->toBe('10.000');
    $movement = $this->movements->forProduct($this->honeyId)[0];
    expect($movement->type)->toBe(MovementType::Entry)
        ->and($movement->note)->toBe('bought from X')
        ->and($movement->recordedBy)->toBe('manager-account')
        ->and($movement->occurredAt->format('c'))->toBe($this->clock->now()->format('c'));
});

it('accepts an entry without a note (R26)', function () {
    ($this->entry)($this->manager, $this->honeyId, '1.500', null);

    expect(($this->available)())->toBe('1.500')
        ->and($this->movements->forProduct($this->honeyId)[0]->note)->toBeNull();
});

it('rejects an entry that is zero or negative (R26)', function (string $quantity, string $error) {
    expect(fn () => ($this->entry)($this->manager, $this->honeyId, $quantity, null))->toThrow($error);
    expect(($this->available)())->toBe('0.000');
})->with([
    'zero' => ['0', InvalidMovementQuantity::class],
    'negative' => ['-1', InvalidQuantity::class],
    'malformed' => ['1.2345', InvalidQuantity::class],
]);

it('records entries and adjustments, and never lets stock go below zero (S23, R26, R27, R28)', function () {
    ($this->entry)($this->manager, $this->honeyId, '10', null);
    ($this->adjust)($this->manager, $this->honeyId, '-1', 'broken jar', null);

    expect(($this->available)())->toBe('9.000');

    expect(fn () => ($this->adjust)($this->manager, $this->honeyId, '-10', 'inventory count', null))
        ->toThrow(NotEnoughStock::class);
    expect(($this->available)())->toBe('9.000')
        ->and($this->movements->forProduct($this->honeyId))->toHaveCount(2);
});

it('lets an adjustment take the stock exactly to zero, or add to it (R27)', function () {
    ($this->entry)($this->manager, $this->honeyId, '2', null);

    ($this->adjust)($this->manager, $this->honeyId, '1.5', 'inventory count', null);
    ($this->adjust)($this->manager, $this->honeyId, '-3.500', 'inventory count', null);

    expect(($this->available)())->toBe('0.000');
});

it('keeps the optional cycle of an adjustment (R27)', function () {
    ($this->entry)($this->manager, $this->honeyId, '5', null);

    ($this->adjust)($this->manager, $this->honeyId, '-1', 'delivered short', 'cycle-1');

    $adjustment = $this->movements->forProduct($this->honeyId)[1];
    expect($adjustment->cycleId)->toBe('cycle-1')->and($adjustment->type)->toBe(MovementType::Adjustment);
});

it('requires a note and a non-zero quantity on an adjustment (R27)', function () {
    ($this->entry)($this->manager, $this->honeyId, '5', null);

    expect(fn () => ($this->adjust)($this->manager, $this->honeyId, '-1', '  ', null))->toThrow(AdjustmentNoteRequired::class);
    expect(fn () => ($this->adjust)($this->manager, $this->honeyId, '0', 'nothing', null))->toThrow(InvalidMovementQuantity::class);
    expect(($this->available)())->toBe('5.000');
});

it('subtracts what is sold from the available stock (R28)', function () {
    ($this->entry)($this->manager, $this->honeyId, '10', null);

    ($this->sell)($this->manager, $this->honeyId, '3', 'order-1');

    expect(($this->available)())->toBe('7.000');
    $sale = $this->movements->forProduct($this->honeyId)[1];
    expect($sale->type)->toBe(MovementType::Sale)->and($sale->orderId)->toBe('order-1')
        ->and($sale->quantity->toString())->toBe('-3.000');
});

it('refuses to sell more than is available and records nothing (R22, R27, S45)', function () {
    ($this->entry)($this->manager, $this->honeyId, '2', null);

    expect(fn () => ($this->sell)($this->manager, $this->honeyId, '2.001', 'order-1'))->toThrow(NotEnoughStock::class);

    expect(($this->available)())->toBe('2.000')
        ->and($this->movements->forProduct($this->honeyId))->toHaveCount(1);
});

it('refuses a second sale once the first took the last of the stock (S45)', function () {
    ($this->entry)($this->manager, $this->honeyId, '1', null);
    ($this->sell)($this->manager, $this->honeyId, '1', 'order-1');

    expect(fn () => ($this->sell)($this->manager, $this->honeyId, '1', 'order-2'))->toThrow(NotEnoughStock::class);
});

it('lists every stock product with its available quantity, zero when it has no movements (R28)', function () {
    $oil = StockProduct::create($this->products->nextId(), 'Oil', 'bottle', Money::ofCents(900), true, null);
    $this->products->add($oil);
    ($this->entry)($this->manager, $this->honeyId, '10', null);
    ($this->sell)($this->manager, $this->honeyId, '4', 'order-1');

    $levels = collect(($this->listStock)($this->manager));

    expect($levels->pluck('name')->all())->toBe(['Honey', 'Oil'])
        ->and($levels->firstWhere('id', $this->honeyId)->available)->toBe('6.000')
        ->and($levels->firstWhere('id', $oil->id)->available)->toBe('0.000')
        ->and($levels->firstWhere('id', $oil->id)->variable)->toBeTrue();
});

it('offers a stock product with its current price, containers and available quantity (R20)', function () {
    ($this->entry)($this->manager, $this->honeyId, '5', null);

    $offer = ($this->offer)($this->honeyId);

    expect($offer->name)->toBe('Honey')
        ->and($offer->unit)->toBe('jar')
        ->and($offer->priceCents)->toBe(650)
        ->and($offer->containersPerUnit)->toBe('2.000')
        ->and($offer->variable)->toBeFalse()
        ->and($offer->active)->toBeTrue()
        ->and($offer->available)->toBe('5.000');
});

it('still offers a deactivated stock product, flagged inactive (S55)', function () {
    $honey = $this->products->get($this->honeyId);
    $honey->deactivate();
    $this->products->add($honey);

    expect(($this->offer)($this->honeyId)->active)->toBeFalse();
});

it('offers a return item with its current amount (R20a)', function () {
    $jar = ReturnItem::create($this->returnItems->nextId(), 'Glass jar', Money::ofCents(50));
    $this->returnItems->add($jar);

    $item = ($this->returnItem)($jar->id);

    expect($item->name)->toBe('Glass jar')->and($item->amountCents)->toBe(50)->and($item->active)->toBeTrue();
});

it('fails on an unknown stock product or return item', function () {
    expect(fn () => ($this->entry)($this->manager, 'missing', '1', null))->toThrow(StockProductNotFound::class);
    expect(fn () => ($this->adjust)($this->manager, 'missing', '1', 'why', null))->toThrow(StockProductNotFound::class);
    expect(fn () => ($this->sell)($this->manager, 'missing', '1', 'order-1'))->toThrow(StockProductNotFound::class);
    expect(fn () => ($this->offer)('missing'))->toThrow(StockProductNotFound::class);
    expect(fn () => ($this->returnItem)('missing'))->toThrow(ReturnItemNotFound::class);
});

it('only lets a manager record entries and adjustments and list the stock (R26, R27, R36a)', function (Role $role) {
    $actor = new Actor('other-account', [$role]);

    expect(fn () => ($this->entry)($actor, $this->honeyId, '1', null))->toThrow(NotAllowed::class);
    expect(fn () => ($this->adjust)($actor, $this->honeyId, '1', 'why', null))->toThrow(NotAllowed::class);
    expect(fn () => ($this->listStock)($actor))->toThrow(NotAllowed::class);
    expect($this->movements->forProduct($this->honeyId))->toBe([]);
})->with([Role::Member, Role::Producer]);

it('still lists a product whose stock exceeds seven integer digits (R28)', function () {
    ($this->entry)($this->manager, $this->honeyId, '9999999', null);
    ($this->entry)($this->manager, $this->honeyId, '9999999', null);

    expect(($this->available)())->toBe('19999998.000');
});
