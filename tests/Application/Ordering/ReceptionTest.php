<?php

use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\FractionalQuantityNotAllowed;
use Guixa\Ordering\Domain\Error\HouseholdInactive;
use Guixa\Ordering\Domain\Error\HouseholdNotFound;
use Guixa\Ordering\Domain\Error\LineMarkedNotDelivered;
use Guixa\Ordering\Domain\Error\ManualAllocationsWouldBeLost;
use Guixa\Ordering\Domain\Error\NobodyOrderedProduct;
use Guixa\Ordering\Domain\Error\ProducerNotInCycle;
use Guixa\Ordering\Domain\Error\ProducerOrderNotSent;
use Guixa\Ordering\Domain\Error\ProductAlreadyOrdered;
use Guixa\Ordering\Domain\Error\ProductNotInReception;
use Guixa\Ordering\Domain\Error\ProductNotOrderable;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\InvalidQuantity;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\Ordering\OrderingWorld;

const RECEPTION_A = '00000000-0000-7000-8000-0000000000e1';
const RECEPTION_B = '00000000-0000-7000-8000-0000000000e2';
const RECEPTION_C = '00000000-0000-7000-8000-0000000000e3';
const RECEPTION_D = '00000000-0000-7000-8000-0000000000e4';
const RECEPTION_GONE = '00000000-0000-7000-8000-0000000000e5';

beforeEach(function () {
    // Thursday 22 October 2026; the pickup is Saturday 7 November and the baker's cutoff Wednesday 4 November 20:00.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    foreach ([RECEPTION_A => 'Household A', RECEPTION_B => 'Household B', RECEPTION_C => 'Household C', RECEPTION_D => 'Household D'] as $id => $name) {
        $this->world->households->know($id, $name);
    }
    $this->world->households->know(RECEPTION_GONE, 'Household Gone', active: false);
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 300, variable: false);
    $this->jam = $this->world->catalog->offer(OrderingWorld::BAKER, 'Jam', 'jar', 400, variable: false);
    $this->cheese = $this->world->catalog->offer(OrderingWorld::BAKER, 'Cheese', 'kg', 1200, variable: true);
    $this->rye = $this->world->catalog->offer(OrderingWorld::BAKER, 'Rye', 'loaf', 350, variable: false);
    $this->apples = $this->world->catalog->offer(OrderingWorld::GROWER, 'Apples', 'kg', 280, variable: true);
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER]);
    $this->households = [
        'A' => new Actor('00000000-0000-7000-8000-0000000000a1', [Role::Member], RECEPTION_A),
        'B' => new Actor('00000000-0000-7000-8000-0000000000a2', [Role::Member], RECEPTION_B),
        'C' => new Actor('00000000-0000-7000-8000-0000000000a3', [Role::Member], RECEPTION_C),
    ];
    $this->householdIds = ['A' => RECEPTION_A, 'B' => RECEPTION_B, 'C' => RECEPTION_C, 'D' => RECEPTION_D];
    $this->minute = 0;
    // Each order is placed a minute after the previous one, so lines have distinct creation moments.
    $this->order = function (string $household, string $productId, string $quantity) {
        $this->world->clock->travelTo('2026-10-22 10:'.str_pad((string) ++$this->minute, 2, '0', STR_PAD_LEFT));
        ($this->world->setOrderLine)($this->households[$household], $this->cycleId, $productId, $quantity);
    };
    $this->send = function () {
        ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
        ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    };
    $this->reception = fn (?Actor $actor = null) => ($this->world->getReception)($actor ?? $this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    $this->product = fn (string $productId) => collect(($this->reception)()->products)->firstWhere('productId', $productId);
    $this->shares = fn (string $productId) => collect(($this->product)($productId)->allocations)->mapWithKeys(fn ($a) => [$a->householdName => $a->allocated])->all();
    $this->setReceived = fn (string $productId, string $received, bool $overwrite = false) => ($this->world->setReceivedTotal)(
        $this->world->manager, $this->cycleId, OrderingWorld::BAKER, $productId, $received, $overwrite,
    );
    $this->allocate = fn (string $productId, string $household, string $quantity) => ($this->world->setAllocation)(
        $this->world->manager, $this->cycleId, OrderingWorld::BAKER, $productId, $this->householdIds[$household], $quantity,
    );
    $this->addUnordered = fn (string $productId, string $received = '0') => ($this->world->addUnorderedProduct)(
        $this->world->manager, $this->cycleId, OrderingWorld::BAKER, $productId, $received,
    );
    // S10: A, B and C ordered 4, 6 and 10 loaves, B's line created before C's.
    $this->orderBreadLikeS10 = function () {
        ($this->order)('A', $this->bread, '4');
        ($this->order)('B', $this->bread, '6');
        ($this->order)('C', $this->bread, '10');
        ($this->send)();
    };
});

// R14 — opening the reception

it('shows the ordered total, a received total equal to it and each household allocated what it ordered (R14, R17)', function () {
    ($this->orderBreadLikeS10)();

    $bread = ($this->product)($this->bread);

    expect(($this->reception)()->cycleId)->toBe($this->cycleId)
        ->and(($this->reception)()->pickupDate)->toBe('2026-11-07')
        ->and([$bread->name, $bread->unit, $bread->ordered, $bread->received, $bread->allocated, $bread->difference, $bread->balanced])
        ->toBe(['Bread', 'loaf', '20.000', '20.000', '20.000', '0.000', true])
        ->and(collect($bread->allocations)->map(fn ($a) => [$a->householdName, $a->ordered, $a->allocated, $a->byHand])->all())
        ->toBe([['Household A', '4.000', '4.000', false], ['Household B', '6.000', '6.000', false], ['Household C', '10.000', '10.000', false]]);
});

it('opens only after the producer order is sent (R14)', function () {
    ($this->order)('A', $this->bread, '4');

    expect(fn () => ($this->reception)())->toThrow(ProducerOrderNotSent::class)
        ->and(fn () => ($this->addUnordered)($this->rye, '3'))->toThrow(ProducerOrderNotSent::class)
        ->and(fn () => ($this->setReceived)($this->bread, '3'))->toThrow(ProducerOrderNotSent::class)
        ->and(fn () => ($this->allocate)($this->bread, 'D', '1'))->toThrow(ProducerOrderNotSent::class);
});

it('is a manager tool for every reception use case (R14)', function () {
    ($this->orderBreadLikeS10)();
    $member = $this->households['A'];
    $producer = OrderingWorld::BAKER;

    expect(fn () => ($this->reception)($member))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->addUnorderedProduct)($member, $this->cycleId, $producer, $this->rye, '3'))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->setReceivedTotal)($member, $this->cycleId, $producer, $this->bread, '3'))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->setAllocation)($member, $this->cycleId, $producer, $this->bread, RECEPTION_A, '3'))->toThrow(NotAllowed::class);
});

it('answers not found for an unknown cycle or a producer that is not in it (R14)', function () {
    ($this->orderBreadLikeS10)();

    expect(fn () => ($this->world->getReception)($this->world->manager, '00000000-0000-7000-8000-0000000000ff', OrderingWorld::BAKER))->toThrow(CycleNotFound::class)
        ->and(fn () => ($this->world->getReception)($this->world->manager, $this->cycleId, '00000000-0000-7000-8000-0000000000b9'))->toThrow(ProducerNotInCycle::class);
});

it('leaves the lines marked will not be delivered out of the reception and out of the claims (R3b, R14)', function () {
    ($this->order)('A', $this->bread, '4');
    ($this->order)('A', $this->jam, '2');
    ($this->order)('B', $this->bread, '6');
    ($this->world->setProductAvailability)(new Actor('00000000-0000-7000-8000-0000000000aa', [Role::Producer], null, OrderingWorld::BAKER), $this->cycleId, $this->jam, false);
    ($this->send)();

    expect(collect(($this->reception)()->products)->pluck('name')->all())->toBe(['Bread']);

    // Jam is marked, so it is not in the reception: it cannot be given a total or an allocation either.
    expect(fn () => ($this->setReceived)($this->jam, '1'))->toThrow(ProductNotInReception::class);
});

it('lists the products of the producer that nobody ordered as options to add, deactivated ones included (R14)', function () {
    ($this->orderBreadLikeS10)();
    $this->world->catalog->deactivate($this->jam);

    expect(collect(($this->reception)()->unorderedOptions)->map(fn ($o) => [$o->name, $o->unit])->all())
        ->toBe([['Cheese', 'kg'], ['Jam', 'jar'], ['Rye', 'loaf']]);
});

// R14 — an unordered product

it('adds an active product nobody ordered with the received total given and no allocations (R14, S40)', function () {
    ($this->orderBreadLikeS10)();

    ($this->addUnordered)($this->rye, '3');

    $rye = ($this->product)($this->rye);
    expect([$rye->ordered, $rye->received, $rye->allocated, $rye->difference, $rye->balanced, $rye->nobodyOrdered, $rye->allocations])
        ->toBe(['0.000', '3.000', '0.000', '3.000', false, true, []])
        ->and(collect(($this->reception)()->unorderedOptions)->pluck('name')->all())->not->toContain('Rye');
});

it('adds an unordered product with a received total of 0 by default (R14)', function () {
    ($this->orderBreadLikeS10)();

    ($this->world->addUnorderedProduct)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->rye);

    expect(($this->product)($this->rye)->received)->toBe('0.000')
        ->and(($this->product)($this->rye)->balanced)->toBeTrue();
});

it('adds only a product of that producer, whether or not it is still active (R14)', function () {
    ($this->orderBreadLikeS10)();
    $this->world->catalog->deactivate($this->rye);

    ($this->addUnordered)($this->rye, '3');

    expect(($this->product)($this->rye)->received)->toBe('3.000')
        ->and(fn () => ($this->addUnordered)($this->apples, '3'))->toThrow(ProductNotOrderable::class)
        ->and(fn () => ($this->addUnordered)('00000000-0000-7000-8000-0000000000ee', '3'))->toThrow(ProductNotOrderable::class);
});

it('adds a product of a producer that was deactivated after its order was sent (R14, S50)', function () {
    ($this->orderBreadLikeS10)();
    $this->world->catalog->deactivate($this->rye);
    $this->world->catalog->deactivate($this->bread);

    ($this->addUnordered)($this->rye, '3');

    expect(collect(($this->reception)()->unorderedOptions)->pluck('name')->all())->toBe(['Cheese', 'Jam'])
        ->and(collect(($this->reception)()->products)->pluck('name')->all())->toBe(['Bread', 'Rye']);
});

it('adds a non-variable product only in whole units (R9, R14)', function () {
    ($this->orderBreadLikeS10)();

    expect(fn () => ($this->addUnordered)($this->rye, '2.5'))->toThrow(FractionalQuantityNotAllowed::class);
});

it('refuses to add a product somebody ordered and leaves its total as it is (R14)', function () {
    ($this->orderBreadLikeS10)();
    ($this->setReceived)($this->bread, '15');

    expect(fn () => ($this->addUnordered)($this->bread, '3'))->toThrow(ProductAlreadyOrdered::class)
        ->and(($this->product)($this->bread)->received)->toBe('15.000');
});

it('replaces the received total of a product nobody ordered when it is added again (R14)', function () {
    ($this->orderBreadLikeS10)();
    ($this->addUnordered)($this->rye, '3');

    ($this->addUnordered)($this->rye, '4');

    expect(($this->product)($this->rye)->received)->toBe('4.000');
});

// R15 — way A

it('splits a shortage proportionally and gives the leftover unit to the earliest line (R15, S10)', function () {
    ($this->orderBreadLikeS10)();

    ($this->setReceived)($this->bread, '15');

    $bread = ($this->product)($this->bread);
    expect(($this->shares)($this->bread))->toBe(['Household A' => '3.000', 'Household B' => '5.000', 'Household C' => '7.000'])
        ->and([$bread->received, $bread->allocated, $bread->difference, $bread->balanced])->toBe(['15.000', '15.000', '0.000', true]);
});

it('splits a surplus on a variable product (R15, S11)', function () {
    ($this->order)('A', $this->cheese, '2');
    ($this->order)('B', $this->cheese, '2');
    ($this->send)();

    ($this->setReceived)($this->cheese, '5');

    expect(($this->shares)($this->cheese))->toBe(['Household A' => '2.500', 'Household B' => '2.500']);
});

it('shares leftover grams one by one to the earliest lines (R15, R17, S12)', function () {
    ($this->order)('A', $this->cheese, '1');
    ($this->order)('B', $this->cheese, '1');
    ($this->order)('C', $this->cheese, '1');
    ($this->send)();

    ($this->setReceived)($this->cheese, '1');

    expect(($this->shares)($this->cheese))->toBe(['Household A' => '0.334', 'Household B' => '0.333', 'Household C' => '0.333'])
        ->and(($this->product)($this->cheese)->balanced)->toBeTrue();
});

it('refuses a received total in fractions for a non-variable product (R9, R15)', function () {
    ($this->orderBreadLikeS10)();

    expect(fn () => ($this->setReceived)($this->bread, '14.5'))->toThrow(FractionalQuantityNotAllowed::class)
        ->and(fn () => ($this->setReceived)($this->bread, '-1'))->toThrow(InvalidQuantity::class);
});

it('refuses way A for a product nobody ordered (R15)', function () {
    ($this->orderBreadLikeS10)();
    ($this->addUnordered)($this->rye, '3');

    expect(fn () => ($this->setReceived)($this->rye, '5'))->toThrow(NobodyOrderedProduct::class);
});

it('refuses a received total for a product that is not in the reception (R15)', function () {
    ($this->orderBreadLikeS10)();

    expect(fn () => ($this->setReceived)($this->rye, '5'))->toThrow(ProductNotInReception::class);
});

it('can set the received total to 0, leaving every household with 0 (R15)', function () {
    ($this->orderBreadLikeS10)();

    ($this->setReceived)($this->bread, '0');

    expect(($this->shares)($this->bread))->toBe(['Household A' => '0.000', 'Household B' => '0.000', 'Household C' => '0.000'])
        ->and(($this->product)($this->bread)->balanced)->toBeTrue();
});

// R16 — way B

it('sets an allocation by hand without changing the received total and flags the product as unbalanced (R16, R17, S13)', function () {
    ($this->orderBreadLikeS10)();
    ($this->setReceived)($this->bread, '15');

    ($this->allocate)($this->bread, 'C', '6');

    $bread = ($this->product)($this->bread);
    expect($bread->received)->toBe('15.000')
        ->and([$bread->allocated, $bread->difference, $bread->balanced, $bread->hasManualAllocations])->toBe(['14.000', '1.000', false, true])
        ->and(collect($bread->allocations)->firstWhere('householdName', 'Household C')->byHand)->toBeTrue()
        ->and(collect($bread->allocations)->firstWhere('householdName', 'Household A')->byHand)->toBeFalse();
});

it('shows a negative difference when more is allocated than received (R17)', function () {
    ($this->orderBreadLikeS10)();
    ($this->setReceived)($this->bread, '15');

    ($this->allocate)($this->bread, 'C', '9');

    expect(($this->product)($this->bread)->difference)->toBe('-2.000');
});

it('allocates in the product step: whole units, or thousandths for a variable product (R16)', function () {
    ($this->order)('A', $this->bread, '4');
    ($this->order)('A', $this->cheese, '1');
    ($this->send)();

    ($this->allocate)($this->cheese, 'A', '0.001');

    expect(fn () => ($this->allocate)($this->bread, 'A', '1.5'))->toThrow(FractionalQuantityNotAllowed::class)
        ->and(fn () => ($this->allocate)($this->cheese, 'A', '-1'))->toThrow(InvalidQuantity::class)
        ->and(fn () => ($this->allocate)($this->cheese, 'A', '0.0001'))->toThrow(InvalidQuantity::class)
        ->and(($this->shares)($this->cheese))->toBe(['Household A' => '0.001']);
});

it('gives a household that did not order a snapshot of the product as it is now (R10, R16, S39)', function () {
    ($this->orderBreadLikeS10)();

    ($this->allocate)($this->bread, 'D', '1');
    $this->world->catalog->change($this->bread, priceCents: 500);

    $line = $this->world->orders->ofHousehold($this->cycleId, RECEPTION_D)->lines()[0];
    expect([$line->productId, $line->name, $line->unitPrice->cents, $line->ordered->toString(), $line->allocated->toString(), $line->allocatedByHand])
        ->toBe([$this->bread, 'Bread', 300, '0.000', '1.000', true])
        ->and(($this->shares)($this->bread))->toHaveKey('Household D', '1.000')
        ->and(collect(($this->product)($this->bread)->allocations)->firstWhere('householdName', 'Household D')->ordered)->toBe('0.000');
});

it('allocates a product deactivated after the order was sent, with the snapshot of it as it is now (R10, R16)', function () {
    ($this->orderBreadLikeS10)();
    $this->world->catalog->change($this->bread, priceCents: 320);
    $this->world->catalog->deactivate($this->bread);

    ($this->allocate)($this->bread, 'D', '1');

    $line = $this->world->orders->ofHousehold($this->cycleId, RECEPTION_D)->lines()[0];
    expect([$line->name, $line->unitPrice->cents, $line->allocated->toString()])->toBe(['Bread', 320, '1.000']);
});

it('allocates only to an active household that exists (R16)', function () {
    ($this->orderBreadLikeS10)();

    expect(fn () => ($this->world->setAllocation)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->bread, RECEPTION_GONE, '1'))
        ->toThrow(HouseholdInactive::class)
        ->and(fn () => ($this->world->setAllocation)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->bread, '00000000-0000-7000-8000-0000000000ee', '1'))
        ->toThrow(HouseholdNotFound::class)
        ->and($this->world->orders->ofHousehold($this->cycleId, RECEPTION_GONE))->toBeNull();
});

it('refuses an allocation for a product that is not in the reception (R16)', function () {
    ($this->orderBreadLikeS10)();

    expect(fn () => ($this->allocate)($this->rye, 'A', '1'))->toThrow(ProductNotInReception::class);
});

it('refuses an allocation to a household whose line is marked will not be delivered, but not to another one (R3b, R16)', function () {
    ($this->order)('A', $this->bread, '4');
    ($this->order)('B', $this->jam, '2');
    ($this->world->setProductAvailability)(new Actor('00000000-0000-7000-8000-0000000000aa', [Role::Producer], null, OrderingWorld::BAKER), $this->cycleId, $this->jam, false);
    ($this->send)();
    ($this->addUnordered)($this->jam, '2');

    expect(fn () => ($this->allocate)($this->jam, 'B', '1'))->toThrow(LineMarkedNotDelivered::class);

    ($this->allocate)($this->jam, 'A', '2');

    expect(($this->shares)($this->jam))->toBe(['Household A' => '2.000']);
});

// R15 / R16 — S31

it('warns before way A overwrites a manual allocation and changes nothing (R15, S31)', function () {
    ($this->orderBreadLikeS10)();
    ($this->setReceived)($this->bread, '15');
    ($this->allocate)($this->bread, 'C', '6');

    try {
        ($this->setReceived)($this->bread, '14');
        $thrown = null;
    } catch (ManualAllocationsWouldBeLost $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(ManualAllocationsWouldBeLost::class)
        ->and($thrown->householdIds)->toBe([RECEPTION_C])
        ->and(($this->product)($this->bread)->received)->toBe('15.000')
        ->and(($this->shares)($this->bread))->toBe(['Household A' => '3.000', 'Household B' => '5.000', 'Household C' => '6.000']);
});

it('re-allocates everything from the ordered quantities when the manager confirms the overwrite (R15, S31)', function () {
    ($this->orderBreadLikeS10)();
    ($this->setReceived)($this->bread, '15');
    ($this->allocate)($this->bread, 'C', '6');

    ($this->setReceived)($this->bread, '14', overwrite: true);

    $bread = ($this->product)($this->bread);
    expect(($this->shares)($this->bread))->toBe(['Household A' => '3.000', 'Household B' => '4.000', 'Household C' => '7.000'])
        ->and([$bread->received, $bread->balanced, $bread->hasManualAllocations])->toBe(['14.000', true, false]);
});

it('does not warn when no allocation of the product was set by hand, even if overwrite is asked for (R15)', function () {
    ($this->orderBreadLikeS10)();
    ($this->setReceived)($this->bread, '15');

    ($this->setReceived)($this->bread, '14');

    expect(($this->product)($this->bread)->received)->toBe('14.000');
});

it('sets a household without a line back to 0 when the manager overwrites (R15, R16)', function () {
    ($this->orderBreadLikeS10)();
    ($this->allocate)($this->bread, 'D', '1');

    expect(fn () => ($this->setReceived)($this->bread, '20'))->toThrow(ManualAllocationsWouldBeLost::class);

    ($this->setReceived)($this->bread, '20', overwrite: true);

    expect(($this->shares)($this->bread))->toBe(['Household A' => '4.000', 'Household B' => '6.000', 'Household C' => '10.000', 'Household D' => '0.000'])
        ->and(($this->product)($this->bread)->balanced)->toBeTrue();
});

// S40 — a product nobody ordered

it('balances a product nobody ordered once the manager allocates what was received (R14, R16, S40)', function () {
    ($this->orderBreadLikeS10)();
    ($this->addUnordered)($this->rye, '3');

    ($this->allocate)($this->rye, 'A', '2');
    ($this->allocate)($this->rye, 'B', '1');

    $rye = ($this->product)($this->rye);
    expect(($this->shares)($this->rye))->toBe(['Household A' => '2.000', 'Household B' => '1.000'])
        ->and([$rye->received, $rye->allocated, $rye->balanced])->toBe(['3.000', '3.000', true])
        ->and($this->world->orders->ofHousehold($this->cycleId, RECEPTION_A)->lines())->toHaveCount(2);
});

// Allocation-only lines are not orders

it('keeps allocation-only lines out of what members ordered, cycle totals and producer totals (R7, R12, R13)', function () {
    ($this->orderBreadLikeS10)();
    ($this->addUnordered)($this->rye, '3');
    ($this->allocate)($this->rye, 'A', '2');
    ($this->allocate)($this->bread, 'D', '1');

    $summary = ($this->world->getCycleSummary)($this->world->manager, $this->cycleId);
    $producerSummary = ($this->world->getProducerCycleSummary)(new Actor('00000000-0000-7000-8000-0000000000aa', [Role::Producer], null, OrderingWorld::BAKER), $this->cycleId);
    $memberOrder = ($this->world->getHouseholdOrder)($this->households['A'], $this->cycleId);

    expect(collect($summary->producers[0]->products)->pluck('name')->all())->toBe(['Bread'])
        ->and(collect($summary->producers[0]->products[0]->households)->pluck('householdName')->all())->toBe(['Household A', 'Household B', 'Household C'])
        ->and(collect($producerSummary->products)->pluck('name')->all())->toBe(['Bread'])
        ->and(collect($memberOrder->lines)->pluck('productId')->all())->toBe([$this->bread])
        ->and(($this->world->listMemberCycles)($this->households['A'])[0]->lineCount)->toBe(1);
});
