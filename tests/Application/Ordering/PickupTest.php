<?php

use Guixa\Ordering\Application\CycleSettlement;
use Guixa\Ordering\Application\MarkProducerDistributed;
use Guixa\Ordering\Application\ValidateReception;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\CycleNotSettled;
use Guixa\Ordering\Domain\Error\FractionalQuantityNotAllowed;
use Guixa\Ordering\Domain\Error\HouseholdInactive;
use Guixa\Ordering\Domain\Error\HouseholdNotFound;
use Guixa\Ordering\Domain\Error\InvalidLineQuantity;
use Guixa\Ordering\Domain\Error\NotEnoughStock;
use Guixa\Ordering\Domain\Error\PickupAlreadyOpen;
use Guixa\Ordering\Domain\Error\PickupNotFound;
use Guixa\Ordering\Domain\Error\PickupNotPending;
use Guixa\Ordering\Domain\Error\ProductNotInPickup;
use Guixa\Ordering\Domain\Error\ReturnItemNotFound;
use Guixa\Ordering\Domain\Error\ReturnLineNotInPickup;
use Guixa\Ordering\Domain\Error\StockLineNotInPickup;
use Guixa\Ordering\Domain\Error\StockProductInactive;
use Guixa\Ordering\Domain\Error\StockProductNotFound;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\PickupStatus;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\InvalidQuantity;
use Guixa\Shared\Domain\Money;
use Illuminate\Auth\Access\AuthorizationException;
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
    $this->memberA = Actor::memberOf('00000000-0000-7000-8000-0000000000a1', PICKUP_A);

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

    expect(fn () => ($this->world->getPickup)($this->memberA, $this->cycleId, PICKUP_A))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->openPickup)($this->memberA, $this->cycleId, PICKUP_K))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->adjustDeliveredQuantity)($this->memberA, $this->cycleId, PICKUP_A, $this->beef, '1'))->toThrow(AuthorizationException::class)
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
        ->and(fn () => ($this->world->listPickups)($this->memberA, $this->cycleId))->toThrow(AuthorizationException::class)
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

// R20, S17 — stock lines

it('locks the Catalog stock product before adding it to a pickup (R1b, S76)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    $this->world->stockProducts->locked = [];

    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey, '2');

    expect($this->world->stockProducts->locked)->toContain($honey);
});

it('adds stock to a pickup with a snapshot of the product, and the total includes it (R20, S16)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');

    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey, '2');
    $pickup = ($this->pickup)();

    expect($pickup->stockLines)->toHaveCount(1)
        ->and($pickup->stockLines[0])->toMatchArray(['stockProductId' => $honey, 'name' => 'Honey', 'unitPriceCents' => 600, 'quantity' => '2.000', 'amountCents' => 1200])
        ->and($pickup->totalCents)->toBe(3200 + 1200);
})->group('R20', 'S16');

it('rejects stock above what is available and leaves the stock and the pickup as they were (R20, S17)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '1');

    expect(fn () => ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey, '2'))->toThrow(NotEnoughStock::class)
        ->and(($this->pickup)()->stockLines)->toBe([])
        ->and($this->world->stockMovements->available($honey)->toString())->toBe('1.000');

    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey, '1');

    expect(($this->pickup)()->stockLines[0]->quantity)->toBe('1.000');
})->group('R20', 'S17');

it('does not reserve stock when it is added: the stock stays until the pickup is completed (R20, D7)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');

    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey, '3');

    expect($this->world->stockMovements->available($honey)->toString())->toBe('5.000');
})->group('R20');

it('adds to the quantity of a stock product already on the pickup, checks the combined quantity and keeps the first price (R20, decision 2)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    $add = fn (string $quantity) => ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey, $quantity);

    $add('2');
    $this->world->stockProducts->change($honey, 'Honey', 'jar', Money::ofCents(900), false, null);
    $add('2');
    $line = ($this->pickup)()->stockLines;

    expect($line)->toHaveCount(1)
        ->and($line[0])->toMatchArray(['quantity' => '4.000', 'unitPriceCents' => 600, 'amountCents' => 2400])
        ->and(fn () => $add('2'))->toThrow(NotEnoughStock::class)
        ->and(($this->pickup)()->stockLines[0]->quantity)->toBe('4.000');
})->group('R20');

it('sells non-variable stock in whole units and variable stock in thousandths, and rejects zero (R20)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    $cheese = $this->world->stockProduct('Cheese', 2000, '5', variable: true, unit: 'kg');
    $add = fn (string $product, string $quantity) => ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $product, $quantity);

    expect(fn () => $add($honey, '1.5'))->toThrow(FractionalQuantityNotAllowed::class)
        ->and(fn () => $add($honey, '0'))->toThrow(InvalidLineQuantity::class)
        ->and(fn () => $add($cheese, '0.0001'))->toThrow(InvalidQuantity::class)
        ->and(fn () => $add($cheese, '-1'))->toThrow(InvalidQuantity::class);

    $add($cheese, '0.250');

    expect(($this->pickup)()->stockLines[0])->toMatchArray(['quantity' => '0.250', 'amountCents' => 500]);
})->group('R20', 'R9');

it('rejects adding a deactivated stock product and an unknown one (decision 1, R20)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    $this->world->deactivateStockProduct($honey);

    expect(fn () => ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey, '1'))->toThrow(StockProductInactive::class)
        ->and(fn () => ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, '00000000-0000-7000-8000-0000000000ff', '1'))->toThrow(StockProductNotFound::class)
        ->and(($this->pickup)()->stockLines)->toBe([]);
})->group('R20');

it('keeps a stock line when its product is deactivated, and still removes it (R20, S55)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey, '1');

    $this->world->deactivateStockProduct($honey);

    expect(($this->pickup)()->stockLines[0])->toMatchArray(['name' => 'Honey', 'amountCents' => 600])
        ->and(($this->pickup)()->totalCents)->toBe(3200 + 600)
        ->and(fn () => ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey, '1'))->toThrow(StockProductInactive::class);

    ($this->world->removeStockFromPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey);

    expect(($this->pickup)()->stockLines)->toBe([])
        ->and(($this->pickup)()->totalCents)->toBe(3200);
})->group('R20', 'S55');

it('removes the whole stock line and rejects a product that is not on the pickup (R20)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey, '3');

    ($this->world->removeStockFromPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey);

    expect(($this->pickup)()->stockLines)->toBe([])
        ->and(fn () => ($this->world->removeStockFromPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey))->toThrow(StockLineNotInPickup::class);
})->group('R20');

it('puts stock on a stock-only pickup (R19, S18)', function () {
    ($this->settle)();
    ($this->open)(PICKUP_K);
    $oil = $this->world->stockProduct('Oil', 1200, '3', unit: 'bottle');

    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_K, $oil, '1');

    expect(($this->pickup)(PICKUP_K)->totalCents)->toBe(1200);
})->group('R20', 'S18');

// R20a — return lines

it('adds returned items, crediting quantity times the amount, and the total includes the credit (R20a, S41)', function () {
    ($this->settle)();
    $jar = $this->world->returnItem('Glass jar', 50);

    ($this->world->addReturnToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $jar, 4);
    $pickup = ($this->pickup)();

    expect($pickup->returnLines)->toHaveCount(1)
        ->and($pickup->returnLines[0])->toMatchArray(['returnItemId' => $jar, 'name' => 'Glass jar', 'amountCents' => 50, 'quantity' => 4, 'creditCents' => 200])
        ->and($pickup->totalCents)->toBe(3200 - 200);
})->group('R20a', 'S41');

it('lets the total go negative when the returns exceed the lines (R20a, R22)', function () {
    ($this->settle)();
    ($this->open)(PICKUP_K);
    $jar = $this->world->returnItem('Glass jar', 50);

    ($this->world->addReturnToPickup)($this->world->manager, $this->cycleId, PICKUP_K, $jar, 2);

    expect(($this->pickup)(PICKUP_K)->totalCents)->toBe(-100);
})->group('R20a');

it('adds to the quantity of a return item already on the pickup and keeps its first amount (R20a, decision 2)', function () {
    ($this->settle)();
    $jar = $this->world->returnItem('Glass jar', 50);
    $add = fn (int $quantity) => ($this->world->addReturnToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $jar, $quantity);

    $add(2);
    $this->world->returnItems->get($jar)->change('Glass jar', Money::ofCents(80));
    $add(3);

    expect(($this->pickup)()->returnLines)->toHaveCount(1)
        ->and(($this->pickup)()->returnLines[0])->toMatchArray(['quantity' => 5, 'amountCents' => 50, 'creditCents' => 250]);
})->group('R20a');

it('rejects a return quantity that is not a whole number of units above zero, and an unknown item (R20a)', function () {
    ($this->settle)();
    $jar = $this->world->returnItem('Glass jar', 50);
    $add = fn (string $item, int $quantity) => ($this->world->addReturnToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $item, $quantity);

    expect(fn () => $add($jar, 0))->toThrow(InvalidLineQuantity::class)
        ->and(fn () => $add($jar, -1))->toThrow(InvalidLineQuantity::class)
        ->and(fn () => $add('00000000-0000-7000-8000-0000000000ff', 1))->toThrow(ReturnItemNotFound::class)
        ->and(($this->pickup)()->returnLines)->toBe([]);
})->group('R20a');

it('accepts a deactivated return item, keeps the line and removes it whole (R20a, decision 1)', function () {
    ($this->settle)();
    $jar = $this->world->returnItem('Glass jar', 50);
    $this->world->deactivateReturnItem($jar);

    ($this->world->addReturnToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $jar, 2);

    expect(($this->pickup)()->returnLines[0]->quantity)->toBe(2);

    ($this->world->removeReturnFromPickup)($this->world->manager, $this->cycleId, PICKUP_A, $jar);

    expect(($this->pickup)()->returnLines)->toBe([])
        ->and(fn () => ($this->world->removeReturnFromPickup)($this->world->manager, $this->cycleId, PICKUP_A, $jar))->toThrow(ReturnLineNotInPickup::class);
})->group('R20a');

// Shared rules of the four use cases

it('lets only a manager change stock and return lines, and answers CycleNotFound or PickupNotFound for an unknown cycle or pickup (R20, R20a)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    $jar = $this->world->returnItem('Glass jar', 50);
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $honey, '1');
    ($this->world->addReturnToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $jar, 1);
    $manager = $this->world->manager;

    expect(fn () => ($this->world->addStockToPickup)($this->memberA, $this->cycleId, PICKUP_A, $honey, '1'))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->removeStockFromPickup)($this->memberA, $this->cycleId, PICKUP_A, $honey))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->addReturnToPickup)($this->memberA, $this->cycleId, PICKUP_A, $jar, 1))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->removeReturnFromPickup)($this->memberA, $this->cycleId, PICKUP_A, $jar))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->addStockToPickup)($manager, '00000000-0000-7000-8000-0000000000ff', PICKUP_A, $honey, '1'))->toThrow(CycleNotFound::class)
        ->and(fn () => ($this->world->addReturnToPickup)($manager, $this->cycleId, '00000000-0000-7000-8000-0000000000ff', $jar, 1))->toThrow(PickupNotFound::class);
})->group('R20', 'R20a');

it('rejects stock and return changes once the pickup is not pending (R20, R20a, R23, R24)', function () {
    $honey = $this->world->stockProduct('Honey', 600, '5');
    $jar = $this->world->returnItem('Glass jar', 50);
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
    $manager = $this->world->manager;

    expect(($this->pickup)()->status)->toBe(PickupStatus::NothingToCollect->value)
        ->and(fn () => ($this->world->addStockToPickup)($manager, $this->cycleId, PICKUP_A, $honey, '1'))->toThrow(PickupNotPending::class)
        ->and(fn () => ($this->world->removeStockFromPickup)($manager, $this->cycleId, PICKUP_A, $honey))->toThrow(PickupNotPending::class)
        ->and(fn () => ($this->world->addReturnToPickup)($manager, $this->cycleId, PICKUP_A, $jar, 1))->toThrow(PickupNotPending::class)
        ->and(fn () => ($this->world->removeReturnFromPickup)($manager, $this->cycleId, PICKUP_A, $jar))->toThrow(PickupNotPending::class);
})->group('R20', 'R20a');

it('answers CycleNotSettled before settlement for the four changes (R19, R20, R20a)', function () {
    $honey = $this->world->stockProduct('Honey', 600, '5');
    $jar = $this->world->returnItem('Glass jar', 50);
    $manager = $this->world->manager;

    expect(fn () => ($this->world->addStockToPickup)($manager, $this->cycleId, PICKUP_A, $honey, '1'))->toThrow(CycleNotSettled::class)
        ->and(fn () => ($this->world->removeStockFromPickup)($manager, $this->cycleId, PICKUP_A, $honey))->toThrow(CycleNotSettled::class)
        ->and(fn () => ($this->world->addReturnToPickup)($manager, $this->cycleId, PICKUP_A, $jar, 1))->toThrow(CycleNotSettled::class)
        ->and(fn () => ($this->world->removeReturnFromPickup)($manager, $this->cycleId, PICKUP_A, $jar))->toThrow(CycleNotSettled::class);
})->group('R20', 'R20a');

it('keeps the containers per unit and the price of a stock line after the product changes (R20)', function () {
    ($this->settle)();
    $cheese = $this->world->stockProduct('Cheese', 204, '5', variable: true, unit: 'kg', containersPerUnit: '2');

    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $cheese, '0.125');
    $this->world->stockProducts->change($cheese, 'Cheese', 'kg', Money::ofCents(999), true, '5');
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $cheese, '0.125');

    $stored = $this->world->orders->ofHousehold($this->cycleId, PICKUP_A)->stockLines()[0];

    expect($stored->containersPerUnit->toString())->toBe('2.000')
        ->and($stored->unitPrice->cents)->toBe(204)
        ->and($stored->quantity->toString())->toBe('0.250');
})->group('R20');

it('rounds each stock line half up to the cent in the total (R20, R22)', function () {
    ($this->settle)();
    $cheese = $this->world->stockProduct('Cheese', 204, '5', variable: true, unit: 'kg');

    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, PICKUP_A, $cheese, '0.125');

    // 0.125 kg x 2,04 € = 25,5 cents, which rounds up to 26.
    expect(($this->pickup)()->stockLines[0]->amountCents)->toBe(26)
        ->and(($this->pickup)()->totalCents)->toBe(3200 + 26);
})->group('R20', 'R22');
