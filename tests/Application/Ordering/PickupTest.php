<?php

use Guixa\Ordering\Application\CycleSettlement;
use Guixa\Ordering\Application\MarkProducerDistributed;
use Guixa\Ordering\Application\ValidateReception;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\CycleNotSettled;
use Guixa\Ordering\Domain\Error\FractionalQuantityNotAllowed;
use Guixa\Ordering\Domain\Error\HouseholdInactive;
use Guixa\Ordering\Domain\Error\HouseholdNotFound;
use Guixa\Ordering\Domain\Error\PickupAlreadyOpen;
use Guixa\Ordering\Domain\Error\PickupNotPending;
use Guixa\Ordering\Domain\Error\ProductNotInPickup;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\PickupStatus;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\InvalidQuantity;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\Ordering\OrderingWorld;

const PICKUP_A = '00000000-0000-7000-8000-0000000000e1';
const PICKUP_K = '00000000-0000-7000-8000-0000000000e2';
const PICKUP_OFF = '00000000-0000-7000-8000-0000000000e3';

beforeEach(function () {
    // Thursday 22 October 2026; the pickup is Saturday 7 November.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->world->households->know(PICKUP_A, 'Household A');
    $this->world->households->know(PICKUP_K, 'Household K');
    $this->world->households->know(PICKUP_OFF, 'Household Off', active: false);
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 300, variable: false);
    $this->beef = $this->world->catalog->offer(OrderingWorld::BAKER, 'Beef', 'kg', 2000, variable: true);
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);
    $this->memberA = new Actor('00000000-0000-7000-8000-0000000000a1', [Role::Member], PICKUP_A);

    $transactions = new ImmediateTransactionRunner;
    $loader = new ReceptionLoader($this->world->orders, $this->world->catalog, $this->world->availability, $this->world->receivedTotals);
    $settlement = new CycleSettlement($this->world->orders, new NotDeliveredMarks($this->world->catalog, $this->world->availability, $this->world->orders));
    $validate = new ValidateReception($this->world->cycles, $transactions, $this->world->clock);
    $distribute = new MarkProducerDistributed($this->world->cycles, $loader, $settlement, $transactions, $this->world->clock);

    // A orders 4 loaves and 1 kg of beef, and the baker delivers exactly that: the cycle is settled.
    $this->settle = function () use ($validate, $distribute) {
        ($this->world->setOrderLine)($this->memberA, $this->cycleId, $this->bread, '4');
        ($this->world->setOrderLine)($this->memberA, $this->cycleId, $this->beef, '1');
        ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
        ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
        ($this->world->setReceivedTotal)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->bread, '4');
        ($this->world->setReceivedTotal)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->beef, '1');
        $validate($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
        $distribute($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    };
    $this->pickup = fn (string $household = PICKUP_A) => ($this->world->getPickup)($this->world->manager, $this->cycleId, $household);
    $this->adjust = fn (string $product, string $quantity, string $household = PICKUP_A) => ($this->world->adjustDeliveredQuantity)(
        $this->world->manager, $this->cycleId, $household, $product, $quantity,
    );
    $this->open = fn (string $household) => ($this->world->openPickup)($this->world->manager, $this->cycleId, $household);
});

// S27 — no pickup before settlement

it('has no pickup before the cycle is settled and a pending one once it is (R19, S27)', function () {
    ($this->world->setOrderLine)($this->memberA, $this->cycleId, $this->bread, '4');

    expect(fn () => ($this->pickup)())->toThrow(CycleNotSettled::class)
        ->and(fn () => ($this->open)(PICKUP_K))->toThrow(CycleNotSettled::class)
        ->and(fn () => ($this->adjust)($this->bread, '3'))->toThrow(CycleNotSettled::class);

    ($this->settle)();

    expect(($this->pickup)()->status)->toBe(PickupStatus::Pending->value);
})->group('S27', 'R19');

it('shows every line with its allocation as the delivered quantity and the total (R19, R21)', function () {
    ($this->settle)();

    $pickup = ($this->pickup)();
    $lines = array_column(array_map(fn ($line) => (array) $line, $pickup->lines), null, 'productId');

    expect($pickup->householdName)->toBe('Household A')
        ->and($pickup->pickupDate)->toBe('2026-11-07')
        ->and($lines[$this->bread])->toMatchArray(['quantity' => '4.000', 'allocated' => '4.000', 'delivered' => '4.000', 'adjusted' => false, 'amountCents' => 1200])
        ->and($lines[$this->beef])->toMatchArray(['allocated' => '1.000', 'delivered' => '1.000', 'amountCents' => 2000])
        ->and($pickup->totalCents)->toBe(3200);
})->group('R19', 'R21');

// S14 — variable weight at pickup

it('adjusts the delivered quantity and keeps the allocation, flagging the line (R19, S14)', function () {
    ($this->settle)();

    ($this->adjust)($this->beef, '1.120');

    $line = collect(($this->pickup)()->lines)->firstWhere('productId', $this->beef);
    $stored = collect($this->world->orders->ofHousehold($this->cycleId, PICKUP_A)->lines())->firstWhere('productId', $this->beef);

    expect($line->delivered)->toBe('1.120')
        ->and($line->allocated)->toBe('1.000')
        ->and($line->adjusted)->toBeTrue()
        ->and($line->amountCents)->toBe(2240)
        ->and(($this->pickup)()->totalCents)->toBe(3200 + 240)
        ->and($stored->allocated->toString())->toBe('1.000');
})->group('R19', 'S14');

it('accepts a delivered quantity of zero (R19)', function () {
    ($this->settle)();

    ($this->adjust)($this->beef, '0');

    expect(collect(($this->pickup)()->lines)->firstWhere('productId', $this->beef)->amountCents)->toBe(0);
})->group('R19');

it('rejects a negative or fractional-for-a-whole-unit delivered quantity and keeps the line as it was (R9, R19)', function () {
    ($this->settle)();

    expect(fn () => ($this->adjust)($this->beef, '-1'))->toThrow(InvalidQuantity::class)
        ->and(fn () => ($this->adjust)($this->bread, '3.5'))->toThrow(FractionalQuantityNotAllowed::class)
        ->and(collect(($this->pickup)()->lines)->every(fn ($line) => ! $line->adjusted))->toBeTrue();
})->group('R19', 'R9');

it('rejects adjusting a product the pickup has no line of (R19)', function () {
    ($this->settle)();

    expect(fn () => ($this->adjust)('00000000-0000-7000-8000-0000000000ff', '1'))->toThrow(ProductNotInPickup::class);
})->group('R19');

it('rejects an adjustment unless the pickup is pending (R19, R23, R24)', function () {
    ($this->world->setOrderLine)($this->memberA, $this->cycleId, $this->beef, '1');
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->setReceivedTotal)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->beef, '0');
    $loader = new ReceptionLoader($this->world->orders, $this->world->catalog, $this->world->availability, $this->world->receivedTotals);
    $transactions = new ImmediateTransactionRunner;
    (new ValidateReception($this->world->cycles, $transactions, $this->world->clock))($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    (new MarkProducerDistributed(
        $this->world->cycles, $loader,
        new CycleSettlement($this->world->orders, new NotDeliveredMarks($this->world->catalog, $this->world->availability, $this->world->orders)),
        $transactions, $this->world->clock,
    ))($this->world->manager, $this->cycleId, OrderingWorld::BAKER);

    expect(($this->pickup)()->status)->toBe(PickupStatus::NothingToCollect->value)
        ->and(fn () => ($this->adjust)($this->beef, '1'))->toThrow(PickupNotPending::class);
})->group('R19', 'R24');

it('lets only a manager adjust, open or read a pickup (R19)', function () {
    ($this->settle)();

    expect(fn () => ($this->world->getPickup)($this->memberA, $this->cycleId, PICKUP_A))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->openPickup)($this->memberA, $this->cycleId, PICKUP_K))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->adjustDeliveredQuantity)($this->memberA, $this->cycleId, PICKUP_A, $this->beef, '1'))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->getPickup)($this->world->manager, '00000000-0000-7000-8000-0000000000ff', PICKUP_A))->toThrow(CycleNotFound::class);
})->group('R19');

// S18 — stock-only pickup

it('opens a pending, empty pickup for an active household without an order (R19, S18)', function () {
    ($this->settle)();

    ($this->open)(PICKUP_K);

    $pickup = ($this->pickup)(PICKUP_K);

    expect($pickup->status)->toBe(PickupStatus::Pending->value)
        ->and($pickup->lines)->toBe([])
        ->and($pickup->totalCents)->toBe(0);
})->group('R19', 'S18');

it('opens a pickup only for a known, active household that has none yet (R19, R24b)', function () {
    ($this->settle)();

    expect(fn () => ($this->open)(PICKUP_OFF))->toThrow(HouseholdInactive::class)
        ->and(fn () => ($this->open)('00000000-0000-7000-8000-0000000000ff'))->toThrow(HouseholdNotFound::class)
        ->and(fn () => ($this->open)(PICKUP_A))->toThrow(PickupAlreadyOpen::class);

    ($this->open)(PICKUP_K);

    expect(fn () => ($this->open)(PICKUP_K))->toThrow(PickupAlreadyOpen::class);
})->group('R19');

// R23a — what members see

it('shows a member the pickup only once the cycle is settled (R23a)', function () {
    ($this->world->setOrderLine)($this->memberA, $this->cycleId, $this->bread, '4');
    ($this->world->setOrderLine)($this->memberA, $this->cycleId, $this->beef, '1');

    expect(($this->world->getHouseholdOrder)($this->memberA, $this->cycleId)->pickup)->toBeNull();

    ($this->settle)();
    ($this->adjust)($this->beef, '1.120');

    $pickup = ($this->world->getHouseholdOrder)($this->memberA, $this->cycleId)->pickup;
    $beef = collect($pickup->lines)->firstWhere('productId', $this->beef);

    expect($pickup->status)->toBe(PickupStatus::Pending->value)
        ->and($beef->quantity)->toBe('1.000')
        ->and($beef->delivered)->toBe('1.120')
        ->and($beef->unitPriceCents)->toBe(2000)
        ->and($beef->amountCents)->toBe(2240)
        ->and($pickup->totalCents)->toBe(3440);
})->group('R23a');

// D16 — the reception flags what pickups changed

it('shows a pickup adjustment in the reception as an unbalanced difference that blocks nothing (R17, R19, S14)', function () {
    ($this->settle)();
    ($this->adjust)($this->beef, '1.120');

    $beef = collect(($this->world->getReception)($this->world->manager, $this->cycleId, OrderingWorld::BAKER)->products)->firstWhere('productId', $this->beef);
    $allocation = $beef->allocations[0];

    expect([$beef->received, $beef->allocated, $beef->delivered, $beef->difference, $beef->balanced, $beef->hasAdjustments])
        ->toBe(['1.000', '1.000', '1.120', '-0.120', false, true])
        ->and([$allocation->allocated, $allocation->delivered, $allocation->adjusted])->toBe(['1.000', '1.120', true]);

    $bread = collect(($this->world->getReception)($this->world->manager, $this->cycleId, OrderingWorld::BAKER)->products)->firstWhere('productId', $this->bread);

    expect([$bread->difference, $bread->balanced, $bread->hasAdjustments])->toBe(['0.000', true, false]);
})->group('R17', 'R19', 'S14');

// Pickups index

it('lists the pickups of a settled cycle by household name with their status and total (R19, S18)', function () {
    ($this->world->setOrderLine)($this->memberA, $this->cycleId, $this->bread, '4');

    expect(fn () => ($this->world->listPickups)($this->world->manager, $this->cycleId))->toThrow(CycleNotSettled::class);

    ($this->settle)();
    ($this->open)(PICKUP_K);
    $pickups = ($this->world->listPickups)($this->world->manager, $this->cycleId);

    expect(array_map(fn ($pickup) => [$pickup->householdName, $pickup->status, $pickup->totalCents], $pickups))
        ->toBe([['Household A', 'pending', 3200], ['Household K', 'pending', 0]])
        ->and(fn () => ($this->world->listPickups)($this->memberA, $this->cycleId))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->listPickups)($this->world->manager, '00000000-0000-7000-8000-0000000000ff'))->toThrow(CycleNotFound::class);
})->group('R19', 'S18');

it('does not flag a line as adjusted when the delivered quantity is typed back to the allocation (R19, D16)', function () {
    ($this->settle)();

    ($this->adjust)($this->beef, '1.120');
    ($this->adjust)($this->beef, '1.000');

    $pickup = collect(($this->pickup)()->lines)->firstWhere('productId', $this->beef);
    $reception = collect(($this->world->getReception)($this->world->manager, $this->cycleId, OrderingWorld::BAKER)->products)->firstWhere('productId', $this->beef);

    expect($pickup->adjusted)->toBeFalse()
        ->and($pickup->delivered)->toBe('1.000')
        ->and($reception->hasAdjustments)->toBeFalse()
        ->and($reception->allocations[0]->adjusted)->toBeFalse()
        ->and($reception->balanced)->toBeTrue();
})->group('R19');
