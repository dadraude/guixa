<?php

use Guixa\Ordering\Application\CompletePickup;
use Guixa\Ordering\Application\CycleSettlement;
use Guixa\Ordering\Application\MarkPickupNotCollected;
use Guixa\Ordering\Application\MarkProducerDistributed;
use Guixa\Ordering\Application\PickupLocker;
use Guixa\Ordering\Application\ReopenProducerOrder;
use Guixa\Ordering\Application\StockSeller;
use Guixa\Ordering\Application\ValidateReception;
use Guixa\Ordering\Domain\Error\CycleClosed;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\CycleNotSettled;
use Guixa\Ordering\Domain\Error\NotEnoughStock;
use Guixa\Ordering\Domain\Error\PickupNotFound;
use Guixa\Ordering\Domain\Error\PickupNotPending;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\PickupStatus;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Ordering\Domain\StockLine;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Guixa\Wallet\Domain\Error\InvalidTopUpAmount;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\Ordering\OrderingWorld;

const COMPLETE_A = '00000000-0000-7000-8000-0000000000e1';
const COMPLETE_K = '00000000-0000-7000-8000-0000000000e2';

beforeEach(function () {
    // Thursday 22 October 2026; the pickup is Saturday 7 November.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->world->households->know(COMPLETE_A, 'Household A');
    $this->world->households->know(COMPLETE_K, 'Household K');
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 300, variable: false);
    $this->beef = $this->world->catalog->offer(OrderingWorld::BAKER, 'Beef', 'kg', 2000, variable: true);
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);
    $this->memberA = new Actor('00000000-0000-7000-8000-0000000000a1', [Role::Member], COMPLETE_A);
    $this->baker = new Actor('00000000-0000-7000-8000-0000000000a2', [Role::Producer], null, OrderingWorld::BAKER);

    $transactions = new ImmediateTransactionRunner;
    $loader = new ReceptionLoader($this->world->orders, $this->world->catalog, $this->world->availability, $this->world->receivedTotals);
    $settlement = new CycleSettlement($this->world->orders, new NotDeliveredMarks($this->world->catalog, $this->world->availability, $this->world->orders));
    $validate = new ValidateReception($this->world->cycles, $transactions, $this->world->clock);
    $distribute = new MarkProducerDistributed($this->world->cycles, $loader, $settlement, $transactions, $this->world->clock);

    // A orders 4 loaves (3.00) and 1 kg of beef (20.00), and the baker delivers exactly that: the pickup totals 32.00.
    $this->settle = function (array $received = ['bread' => '4', 'beef' => '1']) use ($validate, $distribute) {
        ($this->world->setOrderLine)($this->memberA, $this->cycleId, $this->bread, '4');
        ($this->world->setOrderLine)($this->memberA, $this->cycleId, $this->beef, '1');
        ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
        ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
        ($this->world->setReceivedTotal)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->bread, $received['bread']);
        ($this->world->setReceivedTotal)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->beef, $received['beef']);
        $validate($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
        $distribute($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    };
    $this->complete = fn (string $household = COMPLETE_A, ?int $topUpCents = null) => ($this->world->completePickup)(
        $this->world->manager, $this->cycleId, $household, $topUpCents,
    );
    $this->balance = fn (string $household = COMPLETE_A) => $this->world->movements->balanceOf($household)->cents;
    $this->movementsOf = fn (string $household = COMPLETE_A) => array_map(
        fn ($movement) => [$movement->type->value, $movement->amount->cents, $movement->cycleId],
        $this->world->movements->statementOf($household),
    );
    $this->pickupOf = fn (string $household = COMPLETE_A) => ($this->world->getPickup)($this->world->manager, $this->cycleId, $household);
    $this->openPickup = fn (string $household) => ($this->world->openPickup)($this->world->manager, $this->cycleId, $household);
});

it('charges the allocations when the pickup is completed without changes and freezes the pickup (R22, S15)', function () {
    ($this->settle)();

    ($this->complete)();

    expect(($this->movementsOf)())->toBe([['charge', -3200, $this->cycleId]])
        ->and(($this->balance)())->toBe(-3200)
        ->and(($this->pickupOf)()->status)->toBe(PickupStatus::Completed->value)
        ->and(($this->pickupOf)()->totalCents)->toBe(3200);
})->group('R22', 'S15');

it('charges the allocation, not the ordered quantity, when the producer delivers less (R15, R22, S15)', function () {
    ($this->settle)(['bread' => '3', 'beef' => '1']);

    ($this->complete)();

    // A ordered 4 loaves and was allocated 3: 3 x 3.00 for the bread plus 20.00 for the beef.
    expect(($this->balance)())->toBe(-(900 + 2000));
})->group('R15', 'R22', 'S15');

it('charges what the manager recorded as delivered, not what was allocated (R19, R22, S14)', function () {
    ($this->settle)();
    ($this->world->adjustDeliveredQuantity)($this->world->manager, $this->cycleId, COMPLETE_A, $this->beef, '1.120');

    ($this->complete)();

    expect(($this->balance)())->toBe(-3440);
})->group('R22', 'S14');

it('leaves the lines marked will not be delivered out of the charge (R18, R22)', function () {
    ($this->world->setOrderLine)($this->memberA, $this->cycleId, $this->bread, '4');
    ($this->world->setOrderLine)($this->memberA, $this->cycleId, $this->beef, '1');
    ($this->world->setProductAvailability)($this->baker, $this->cycleId, $this->beef, false);
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->setReceivedTotal)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->bread, '4');
    (new ValidateReception($this->world->cycles, new ImmediateTransactionRunner, $this->world->clock))($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    (new MarkProducerDistributed(
        $this->world->cycles,
        new ReceptionLoader($this->world->orders, $this->world->catalog, $this->world->availability, $this->world->receivedTotals),
        new CycleSettlement($this->world->orders, new NotDeliveredMarks($this->world->catalog, $this->world->availability, $this->world->orders)),
        new ImmediateTransactionRunner,
        $this->world->clock,
    ))($this->world->manager, $this->cycleId, OrderingWorld::BAKER);

    ($this->complete)();

    expect(($this->balance)())->toBe(-1200);
})->group('R18', 'R22');

it('adds the stock lines to the charge and decreases the stock (R22, S16)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, COMPLETE_A, $honey, '2');

    ($this->complete)();

    expect(($this->balance)())->toBe(-4400)
        ->and($this->world->stockMovements->available($honey)->toString())->toBe('3.000');
})->group('R22', 'S16');

it('charges a stock-only pickup of a household without an order (S18)', function () {
    ($this->settle)();
    $oil = $this->world->stockProduct('Oil', 1200, '3', unit: 'bottle');
    ($this->openPickup)(COMPLETE_K);
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, COMPLETE_K, $oil, '1');

    ($this->complete)(COMPLETE_K);

    expect(($this->balance)(COMPLETE_K))->toBe(-1200)
        ->and($this->world->stockMovements->available($oil)->toString())->toBe('2.000');
})->group('S18');

it('credits the returned items against the total (R20a, R22, S41)', function () {
    ($this->settle)();
    $jar = $this->world->returnItem('Glass jar', 50);
    ($this->world->addReturnToPickup)($this->world->manager, $this->cycleId, COMPLETE_A, $jar, 4);

    ($this->complete)();

    expect(($this->balance)())->toBe(-3000);
})->group('R22', 'S41');

it('records a negative total as a credit and a total of zero as no movement (R22)', function () {
    ($this->settle)();
    $jar = $this->world->returnItem('Glass jar', 50);
    ($this->openPickup)(COMPLETE_K);
    ($this->world->addReturnToPickup)($this->world->manager, $this->cycleId, COMPLETE_K, $jar, 2);
    $empty = '00000000-0000-7000-8000-0000000000e4';
    $this->world->households->know($empty, 'Household Empty');
    ($this->openPickup)($empty);

    ($this->complete)(COMPLETE_K);
    ($this->complete)($empty);

    expect(($this->movementsOf)(COMPLETE_K))->toBe([['credit', 100, $this->cycleId]])
        ->and(($this->balance)(COMPLETE_K))->toBe(100)
        ->and(($this->movementsOf)($empty))->toBe([])
        ->and(($this->pickupOf)($empty)->status)->toBe(PickupStatus::Completed->value);
})->group('R22');

it('rounds each line to the cent, half up, and charges the sum of the lines shown (R22, S53)', function () {
    $salt = $this->world->catalog->offer(OrderingWorld::BAKER, 'Salt', 'kg', 100, variable: true);
    $pepper = $this->world->catalog->offer(OrderingWorld::BAKER, 'Pepper', 'kg', 100, variable: true);
    ($this->world->setOrderLine)($this->memberA, $this->cycleId, $salt, '0.125');
    ($this->world->setOrderLine)($this->memberA, $this->cycleId, $pepper, '0.125');
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->setReceivedTotal)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $salt, '0.125');
    ($this->world->setReceivedTotal)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $pepper, '0.125');
    (new ValidateReception($this->world->cycles, new ImmediateTransactionRunner, $this->world->clock))($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    (new MarkProducerDistributed(
        $this->world->cycles,
        new ReceptionLoader($this->world->orders, $this->world->catalog, $this->world->availability, $this->world->receivedTotals),
        new CycleSettlement($this->world->orders, new NotDeliveredMarks($this->world->catalog, $this->world->availability, $this->world->orders)),
        new ImmediateTransactionRunner,
        $this->world->clock,
    ))($this->world->manager, $this->cycleId, OrderingWorld::BAKER);

    ($this->complete)();

    // Each 0.125 kg at 1.00 is 0.125, rounded to 0.13 per line: 0.26. Rounding the 0.25 total would charge 0.25.
    expect(($this->balance)())->toBe(-26);
})->group('R22', 'S53');

it('rejects a stock line that exceeds the stock at that moment and records nothing (R22, R28, S45)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    ($this->openPickup)(COMPLETE_K);
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, COMPLETE_A, $honey, '3');
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, COMPLETE_K, $honey, '3');
    ($this->complete)();

    expect(fn () => ($this->complete)(COMPLETE_K, 4000))->toThrow(NotEnoughStock::class)
        ->and(($this->movementsOf)(COMPLETE_K))->toBe([])
        ->and($this->world->stockMovements->available($honey)->toString())->toBe('2.000')
        ->and(($this->pickupOf)(COMPLETE_K)->status)->toBe(PickupStatus::Pending->value);
})->group('R22', 'S45');

it('records a top-up with no cycle before the charge (R21, R29, S19)', function () {
    ($this->settle)();
    ($this->world->recordTopUp)($this->world->manager, COMPLETE_A, 500);

    ($this->complete)(COMPLETE_A, 4000);

    expect(($this->balance)())->toBe(500 + 4000 - 3200)
        ->and(($this->movementsOf)())->toContain(['top_up', 4000, null], ['charge', -3200, $this->cycleId]);
})->group('S19');

it('lets the balance go negative when there is no top-up (S21)', function () {
    ($this->settle)();
    ($this->world->recordTopUp)($this->world->manager, COMPLETE_A, 500);

    ($this->complete)();

    expect(($this->balance)())->toBe(500 - 3200);
})->group('S21');

it('rejects a top-up that is not positive and leaves the pickup pending, with its stock line, and records no wallet movement (R29)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, COMPLETE_A, $honey, '2');

    expect(fn () => ($this->complete)(COMPLETE_A, 0))->toThrow(InvalidTopUpAmount::class)
        ->and(fn () => ($this->complete)(COMPLETE_A, -100))->toThrow(InvalidTopUpAmount::class)
        ->and(($this->pickupOf)()->status)->toBe(PickupStatus::Pending->value)
        ->and(($this->pickupOf)()->stockLines)->toHaveCount(1)
        ->and(($this->movementsOf)())->toBe([]);

    // The in-memory ledger has no rollback: the database test proves the stock sold before the rejection is undone.
})->group('R29');

it('rejects completing a pickup twice and changes neither the wallet nor the stock (R23, S22)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, COMPLETE_A, $honey, '2');
    ($this->complete)();

    expect(fn () => ($this->complete)(COMPLETE_A, 1000))->toThrow(PickupNotPending::class)
        ->and(($this->balance)())->toBe(-4400)
        ->and($this->world->stockMovements->available($honey)->toString())->toBe('3.000');
})->group('R23', 'S22');

it('freezes the pickup: its lines and stock can no longer change (R23)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    ($this->complete)();

    expect(fn () => ($this->world->adjustDeliveredQuantity)($this->world->manager, $this->cycleId, COMPLETE_A, $this->beef, '2'))->toThrow(PickupNotPending::class)
        ->and(fn () => ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, COMPLETE_A, $honey, '1'))->toThrow(PickupNotPending::class);
})->group('R23');

it('completes a late pickup after the pickup date with the next cycle already open (R19, S26)', function () {
    ($this->settle)();
    $this->world->clock->travelTo('2026-11-11 10:00');
    ($this->world->openCycle)($this->world->manager, '2026-11-14', [OrderingWorld::BAKER]);

    expect(($this->balance)())->toBe(0);

    ($this->complete)();

    expect(($this->movementsOf)())->toBe([['charge', -3200, $this->cycleId]]);
})->group('R19', 'S26');

it('can be done by a manager only, and only for a settled cycle and an existing pickup (R22)', function () {
    $member = new Actor('00000000-0000-7000-8000-0000000000a1', [Role::Member], COMPLETE_A);
    $complete = fn (Actor $actor, string $cycle, string $household) => ($this->world->completePickup)($actor, $cycle, $household, null);

    expect(fn () => $complete($this->world->manager, $this->cycleId, COMPLETE_A))->toThrow(CycleNotSettled::class)
        ->and(fn () => $complete($this->world->manager, '00000000-0000-7000-8000-0000000000ff', COMPLETE_A))->toThrow(CycleNotFound::class);

    ($this->settle)();

    expect(fn () => $complete($member, $this->cycleId, COMPLETE_A))->toThrow(NotAllowed::class)
        ->and(fn () => $complete($this->world->manager, $this->cycleId, COMPLETE_K))->toThrow(PickupNotFound::class)
        ->and(($this->movementsOf)())->toBe([]);
})->group('R22');

it('sells the stock lines in ascending stock product id order, whatever order they were added in (R22, plan locking notes)', function () {
    ($this->settle)();
    $ids = [$this->world->stockProduct('Honey', 600, '5'), $this->world->stockProduct('Cheese', 900, '5')];
    sort($ids);
    [$low, $high] = $ids;
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, COMPLETE_A, $high, '1');
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, COMPLETE_A, $low, '1');
    $sold = new class implements StockSeller
    {
        /** @var list<string> */
        public array $order = [];

        public function sell(Actor $actor, StockLine $line, string $orderId): void
        {
            $this->order[] = $line->stockProductId;
        }
    };
    $completePickup = new CompletePickup(
        new PickupLocker($this->world->cycles, $this->world->orders),
        $this->world->orders,
        $sold,
        $this->world->walletLedger,
        new ImmediateTransactionRunner,
        $this->world->clock,
    );

    $completePickup($this->world->manager, $this->cycleId, COMPLETE_A);

    expect($sold->order)->toBe([$low, $high]);
})->group('R22');

it('completes a pickup whose stock product was deactivated after the line was added (R20, R22, S55)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    ($this->openPickup)(COMPLETE_K);
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, COMPLETE_K, $honey, '1');
    $this->world->deactivateStockProduct($honey);

    ($this->complete)(COMPLETE_K);

    expect(($this->movementsOf)(COMPLETE_K))->toBe([['charge', -600, $this->cycleId]])
        ->and($this->world->stockMovements->available($honey)->toString())->toBe('4.000');
})->group('R20', 'S55');

it('charges only the settled cycle and leaves the household order in an overlapping cycle editable (R4, R22, S9)', function () {
    ($this->settle)();
    $cycleB = ($this->world->openCycle)($this->world->manager, '2026-11-14', [OrderingWorld::BAKER]);
    ($this->world->setOrderLine)($this->memberA, $cycleB, $this->bread, '2');

    ($this->complete)();
    ($this->world->setOrderLine)($this->memberA, $cycleB, $this->bread, '3');

    $lineInB = $this->world->orders->ofHousehold($cycleB, COMPLETE_A)->lines()[0];

    expect(($this->movementsOf)())->toBe([['charge', -3200, $this->cycleId]])
        ->and($lineInB->ordered->toString())->toBe('3.000')
        ->and($this->world->orders->ofHousehold($cycleB, COMPLETE_A)->pickup())->toBe(PickupStatus::None);
})->group('R4', 'S9');

it('marks an absent household not collected, charges adjusted quantities and drops stock and returns (R24a, S42)', function () {
    ($this->settle)();
    $honey = $this->world->stockProduct('Honey', 600, '5');
    $jar = $this->world->returnItem('Glass jar', 50);
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, COMPLETE_A, $honey, '2');
    ($this->world->addReturnToPickup)($this->world->manager, $this->cycleId, COMPLETE_A, $jar, 4);
    ($this->world->adjustDeliveredQuantity)($this->world->manager, $this->cycleId, COMPLETE_A, $this->beef, '1.120');
    $mark = new MarkPickupNotCollected(
        new PickupLocker($this->world->cycles, $this->world->orders),
        $this->world->orders,
        $this->world->walletLedger,
        new ImmediateTransactionRunner,
        $this->world->clock,
    );

    $mark($this->world->manager, $this->cycleId, COMPLETE_A);

    expect(($this->movementsOf)())->toBe([['charge', -3440, $this->cycleId]])
        ->and(($this->pickupOf)()->status)->toBe('not_collected')
        ->and(($this->pickupOf)()->stockLines)->toBe([])
        ->and(($this->pickupOf)()->returnLines)->toBe([])
        ->and($this->world->stockMovements->available($honey)->toString())->toBe('5.000')
        ->and(fn () => $mark($this->world->manager, $this->cycleId, COMPLETE_A))->toThrow(PickupNotPending::class)
        ->and(($this->balance)())->toBe(-3440);
})->group('R24a', 'S42');

it('closes the cycle after its last pickup and refuses reopening or opening another pickup (R24b, S46, S54)', function () {
    ($this->settle)();
    ($this->complete)();
    $reopen = new ReopenProducerOrder($this->world->cycles, new ImmediateTransactionRunner);

    expect(fn () => $reopen($this->world->manager, $this->cycleId, OrderingWorld::BAKER))
        ->toThrow(CycleClosed::class)
        ->and(fn () => ($this->openPickup)(COMPLETE_K))->toThrow(CycleClosed::class);
})->group('R24b', 'S46', 'S54');

it('keeps closed cycle participation and member order changes final (R24b)', function () {
    ($this->settle)();
    ($this->complete)();

    expect(fn () => ($this->world->addParticipant)($this->world->manager, $this->cycleId, OrderingWorld::GROWER))->toThrow(CycleClosed::class)
        ->and(fn () => ($this->world->removeMarkedLine)($this->memberA, $this->cycleId, $this->bread))->toThrow(CycleClosed::class);
})->group('R24b');
