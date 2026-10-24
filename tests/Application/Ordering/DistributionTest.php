<?php

use Guixa\Ordering\Application\CycleSettlement;
use Guixa\Ordering\Application\MarkProducerDistributed;
use Guixa\Ordering\Application\ReopenProducerOrder;
use Guixa\Ordering\Application\ValidateReception;
use Guixa\Ordering\Domain\Error\CycleAlreadySettled;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\ProducerDistributed;
use Guixa\Ordering\Domain\Error\ProducerNotDistributed;
use Guixa\Ordering\Domain\Error\ProducerNotInCycle;
use Guixa\Ordering\Domain\Error\ProducerOrderNotSent;
use Guixa\Ordering\Domain\Error\ReceptionNotValidated;
use Guixa\Ordering\Domain\Error\ReceptionUnbalanced;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\PickupStatus;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\Ordering\OrderingWorld;

const DIST_A = '00000000-0000-7000-8000-0000000000e1';
const DIST_B = '00000000-0000-7000-8000-0000000000e2';
const DIST_C = '00000000-0000-7000-8000-0000000000e3';

beforeEach(function () {
    // Thursday 22 October 2026; the pickup is Saturday 7 November.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    foreach ([DIST_A => 'Household A', DIST_B => 'Household B', DIST_C => 'Household C'] as $id => $name) {
        $this->world->households->know($id, $name);
    }
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 300, variable: false);
    $this->rye = $this->world->catalog->offer(OrderingWorld::BAKER, 'Rye', 'loaf', 350, variable: false);
    $this->apples = $this->world->catalog->offer(OrderingWorld::GROWER, 'Apples', 'kg', 280, variable: true);
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER]);
    $this->households = [
        'A' => new Actor('00000000-0000-7000-8000-0000000000a1', [Role::Member], DIST_A),
        'B' => new Actor('00000000-0000-7000-8000-0000000000a2', [Role::Member], DIST_B),
        'C' => new Actor('00000000-0000-7000-8000-0000000000a3', [Role::Member], DIST_C),
    ];
    $this->householdIds = ['A' => DIST_A, 'B' => DIST_B, 'C' => DIST_C];

    $transactions = new ImmediateTransactionRunner;
    $loader = new ReceptionLoader($this->world->orders, $this->world->catalog, $this->world->availability, $this->world->receivedTotals);
    $settlement = new CycleSettlement($this->world->orders, new NotDeliveredMarks($this->world->catalog, $this->world->availability, $this->world->orders));
    $this->validateReception = new ValidateReception($this->world->cycles, $transactions, $this->world->clock);
    $this->markDistributed = new MarkProducerDistributed($this->world->cycles, $loader, $settlement, $transactions, $this->world->clock);
    $this->reopenProducerOrder = new ReopenProducerOrder($this->world->cycles, $transactions);

    $this->minute = 0;
    // Each order is placed a minute after the previous one, so lines have distinct creation moments.
    $this->order = function (string $household, string $productId, string $quantity) {
        $this->world->clock->travelTo('2026-10-22 10:'.str_pad((string) ++$this->minute, 2, '0', STR_PAD_LEFT));
        ($this->world->setOrderLine)($this->households[$household], $this->cycleId, $productId, $quantity);
    };
    $this->send = function (string $producerId = OrderingWorld::BAKER) {
        ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, $producerId);
        ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, $producerId);
    };
    $this->validate = fn (string $producerId = OrderingWorld::BAKER, ?Actor $actor = null) => ($this->validateReception)($actor ?? $this->world->manager, $this->cycleId, $producerId);
    $this->distribute = fn (string $producerId = OrderingWorld::BAKER, ?Actor $actor = null) => ($this->markDistributed)($actor ?? $this->world->manager, $this->cycleId, $producerId);
    $this->reopen = fn (string $producerId = OrderingWorld::BAKER, ?Actor $actor = null) => ($this->reopenProducerOrder)($actor ?? $this->world->manager, $this->cycleId, $producerId);
    $this->reception = fn (string $producerId = OrderingWorld::BAKER) => ($this->world->getReception)($this->world->manager, $this->cycleId, $producerId);
    $this->setReceived = fn (string $productId, string $received, string $producerId = OrderingWorld::BAKER) => ($this->world->setReceivedTotal)(
        $this->world->manager, $this->cycleId, $producerId, $productId, $received,
    );
    $this->allocate = fn (string $productId, string $household, string $quantity) => ($this->world->setAllocation)(
        $this->world->manager, $this->cycleId, OrderingWorld::BAKER, $productId, $this->householdIds[$household], $quantity,
    );
    $this->settled = fn () => $this->world->cycles->get($this->cycleId)->isSettled();
    $this->pickup = fn (string $household) => $this->world->orders->ofHousehold($this->cycleId, $this->householdIds[$household])->pickup();
    $this->dropped = fn (string $household) => collect($this->world->orders->ofHousehold($this->cycleId, $this->householdIds[$household])->lines())
        ->filter(fn ($line) => $line->dropped)->map(fn ($line) => $line->productId)->values()->all();
    $this->onlyBaker = fn () => ($this->world->stopParticipating)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);
    // A, B and C ordered 4, 6 and 10 loaves (S10): the baker's order is sent and its reception is balanced.
    $this->bakerSent = function () {
        ($this->order)('A', $this->bread, '4');
        ($this->order)('B', $this->bread, '6');
        ($this->order)('C', $this->bread, '10');
        ($this->send)();
    };
});

// R17a, S47 — validating

it('rejects distributing a balanced reception that is not validated, and accepts it once validated (R17a, R18, S47)', function () {
    ($this->bakerSent)();

    expect(fn () => ($this->distribute)())->toThrow(ReceptionNotValidated::class);

    ($this->validate)();
    ($this->distribute)();

    expect(($this->reception)()->distributed)->toBeTrue();
})->group('S47', 'R17a');

it('clears the validation when a received total changes (R17a, S47)', function () {
    ($this->bakerSent)();
    ($this->validate)();
    expect(($this->reception)()->validated)->toBeTrue();

    ($this->setReceived)($this->bread, '15');

    expect(($this->reception)()->validated)->toBeFalse()
        ->and(fn () => ($this->distribute)())->toThrow(ReceptionNotValidated::class);
})->group('S47', 'R17a');

it('clears the validation when a product nobody ordered gets a received total (R14, R17a)', function () {
    ($this->bakerSent)();
    ($this->validate)();

    ($this->world->addUnorderedProduct)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->rye, '3');

    expect(($this->reception)()->validated)->toBeFalse();
})->group('R17a');

it('keeps the validation when an allocation changes, since no received total changed (R16, R17a)', function () {
    ($this->bakerSent)();
    ($this->validate)();

    ($this->allocate)($this->bread, 'C', '9');

    expect(($this->reception)()->validated)->toBeTrue();
})->group('R17a');

it('validates only a sent producer order, and only for a manager (R14, R17a)', function () {
    ($this->order)('A', $this->bread, '4');

    expect(fn () => ($this->validate)())->toThrow(ProducerOrderNotSent::class);

    ($this->send)();

    expect(fn () => ($this->validate)(OrderingWorld::BAKER, $this->households['A']))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->validateReception)($this->world->manager, '00000000-0000-7000-8000-0000000000ff', OrderingWorld::BAKER))->toThrow(CycleNotFound::class)
        ->and(fn () => ($this->validate)('00000000-0000-7000-8000-0000000000b9'))->toThrow(ProducerNotInCycle::class);
})->group('R17a');

// S13 — balance

it('rejects distributing while a product is unbalanced, even when validated, until it is balanced (R17, R18, S13)', function () {
    ($this->bakerSent)();
    ($this->setReceived)($this->bread, '15');
    ($this->allocate)($this->bread, 'C', '6');
    ($this->validate)();

    expect(fn () => ($this->distribute)())->toThrow(ReceptionUnbalanced::class);

    ($this->allocate)($this->bread, 'C', '7');
    ($this->distribute)();

    expect(($this->reception)()->distributed)->toBeTrue();
})->group('S13');

// S40 — a product nobody ordered

it('distributes a reception whose product nobody ordered once it is allocated by hand (R14, R16, R18, S40)', function () {
    ($this->bakerSent)();
    ($this->world->addUnorderedProduct)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->rye, '3');
    ($this->validate)();

    expect(fn () => ($this->distribute)())->toThrow(ReceptionUnbalanced::class);

    ($this->allocate)($this->rye, 'A', '2');
    ($this->allocate)($this->rye, 'B', '1');
    ($this->distribute)();

    $rye = collect(($this->reception)()->products)->firstWhere('productId', $this->rye);
    expect($rye->balanced)->toBeTrue()
        ->and(collect($rye->allocations)->mapWithKeys(fn ($a) => [$a->householdName => $a->allocated])->all())
        ->toBe(['Household A' => '2.000', 'Household B' => '1.000']);
})->group('S40');

// R18 — distributed receptions are read-only

it('makes a distributed reception read-only (R18)', function () {
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();
    $manager = $this->world->manager;

    expect(fn () => ($this->setReceived)($this->bread, '15'))->toThrow(ProducerDistributed::class)
        ->and(fn () => ($this->allocate)($this->bread, 'A', '1'))->toThrow(ProducerDistributed::class)
        ->and(fn () => ($this->world->addUnorderedProduct)($manager, $this->cycleId, OrderingWorld::BAKER, $this->rye, '3'))->toThrow(ProducerDistributed::class)
        ->and(fn () => ($this->validate)())->toThrow(ProducerDistributed::class)
        ->and(fn () => ($this->distribute)())->toThrow(ProducerDistributed::class)
        ->and(collect(($this->reception)()->products)->firstWhere('productId', $this->bread)->received)->toBe('20.000');
})->group('R18');

it('leaves the reception of a producer that is not distributed editable while another is distributed (R18)', function () {
    ($this->bakerSent)();
    ($this->order)('A', $this->apples, '2');
    ($this->send)(OrderingWorld::GROWER);
    ($this->validate)();
    ($this->distribute)();

    ($this->setReceived)($this->apples, '1', OrderingWorld::GROWER);

    expect(collect(($this->reception)(OrderingWorld::GROWER)->products)->firstWhere('productId', $this->apples)->received)->toBe('1.000');
})->group('R18');

it('lets only a manager distribute or reopen (R18)', function () {
    ($this->bakerSent)();
    ($this->validate)();

    expect(fn () => ($this->distribute)(OrderingWorld::BAKER, $this->households['A']))->toThrow(NotAllowed::class);

    ($this->distribute)();

    expect(fn () => ($this->reopen)(OrderingWorld::BAKER, $this->households['A']))->toThrow(NotAllowed::class);
})->group('R18');

it('distributes only a sent producer order of the cycle (R14, R18)', function () {
    ($this->order)('A', $this->bread, '4');

    expect(fn () => ($this->distribute)())->toThrow(ProducerOrderNotSent::class)
        ->and(fn () => ($this->markDistributed)($this->world->manager, '00000000-0000-7000-8000-0000000000ff', OrderingWorld::BAKER))->toThrow(CycleNotFound::class)
        ->and(fn () => ($this->distribute)('00000000-0000-7000-8000-0000000000b9'))->toThrow(ProducerNotInCycle::class);
})->group('R18');

// S27 — settling

it('settles the cycle only when the last participating producer is distributed (R18, S27)', function () {
    ($this->bakerSent)();
    ($this->order)('A', $this->apples, '2');
    ($this->send)(OrderingWorld::GROWER);
    ($this->validate)();
    ($this->distribute)();

    expect(($this->settled)())->toBeFalse();

    ($this->validate)(OrderingWorld::GROWER);
    ($this->distribute)(OrderingWorld::GROWER);

    expect(($this->settled)())->toBeTrue();
})->group('S27');

it('makes every household with an allocation have a pending pickup once settled (R19, R24, S27)', function () {
    ($this->onlyBaker)();
    ($this->bakerSent)();
    ($this->validate)();

    expect(($this->pickup)('A'))->toBe(PickupStatus::None);

    ($this->distribute)();

    expect(($this->settled)())->toBeTrue()
        ->and([($this->pickup)('A'), ($this->pickup)('B'), ($this->pickup)('C')])->toBe([PickupStatus::Pending, PickupStatus::Pending, PickupStatus::Pending]);
})->group('S27');

// S28 — non-participating producers and marked lines are dropped

it('drops the lines of a producer that stopped participating and never charges them (R6c, R18, S28)', function () {
    ($this->order)('A', $this->bread, '4');
    ($this->order)('A', $this->apples, '2');
    ($this->world->stopParticipating)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);
    ($this->send)();
    ($this->validate)();

    ($this->distribute)();

    expect(($this->settled)())->toBeTrue()
        ->and(($this->dropped)('A'))->toBe([$this->apples])
        ->and(($this->pickup)('A'))->toBe(PickupStatus::Pending);
})->group('S28');

it('drops the lines stored as will not be delivered (R3b, R18, S28)', function () {
    ($this->onlyBaker)();
    ($this->order)('A', $this->bread, '4');
    ($this->order)('A', $this->rye, '1');
    ($this->world->catalog->deactivate($this->rye));
    ($this->send)();
    ($this->validate)();

    ($this->distribute)();

    expect(($this->dropped)('A'))->toBe([$this->rye]);
})->group('S28');

it('leaves the lines of a distributed producer alone when the cycle settles (R18)', function () {
    ($this->onlyBaker)();
    ($this->bakerSent)();
    ($this->validate)();

    ($this->distribute)();

    expect(($this->dropped)('A'))->toBe([]);
})->group('R18');

// S20 — nothing to collect

it('closes a pickup whose every allocation is 0 as nothing to collect when the cycle settles (R24, S20)', function () {
    ($this->onlyBaker)();
    ($this->order)('A', $this->bread, '4');
    ($this->order)('B', $this->rye, '2');
    ($this->send)();
    ($this->setReceived)($this->rye, '0');
    ($this->validate)();

    ($this->distribute)();

    expect(($this->pickup)('A'))->toBe(PickupStatus::Pending)
        ->and(($this->pickup)('B'))->toBe(PickupStatus::NothingToCollect);
})->group('S20', 'R24');

it('closes a pickup whose only lines were dropped as nothing to collect (R18, R24, S28)', function () {
    ($this->order)('A', $this->bread, '4');
    ($this->order)('B', $this->apples, '2');
    ($this->world->stopParticipating)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);
    ($this->send)();
    ($this->validate)();

    ($this->distribute)();

    expect(($this->pickup)('B'))->toBe(PickupStatus::NothingToCollect);
})->group('S28', 'R24');

// S29 — reopening before settlement

it('reopens a distributed producer before settlement, recalculates and settles once distributed again (R18, S29)', function () {
    ($this->bakerSent)();
    ($this->order)('A', $this->apples, '2');
    ($this->send)(OrderingWorld::GROWER);
    ($this->validate)();
    ($this->distribute)();

    ($this->reopen)();
    ($this->setReceived)($this->bread, '15');

    $shares = collect(collect(($this->reception)()->products)->firstWhere('productId', $this->bread)->allocations)->mapWithKeys(fn ($a) => [$a->householdName => $a->allocated])->all();
    expect($shares)->toBe(['Household A' => '3.000', 'Household B' => '5.000', 'Household C' => '7.000'])
        ->and(($this->reception)()->distributed)->toBeFalse();

    ($this->validate)();
    ($this->distribute)();
    ($this->validate)(OrderingWorld::GROWER);
    ($this->distribute)(OrderingWorld::GROWER);

    expect(($this->settled)())->toBeTrue();
})->group('S29');

it('does not settle while a reopened producer is not distributed again (R18, S29)', function () {
    ($this->bakerSent)();
    ($this->order)('A', $this->apples, '2');
    ($this->send)(OrderingWorld::GROWER);
    ($this->validate)();
    ($this->distribute)();
    ($this->reopen)();

    ($this->validate)(OrderingWorld::GROWER);
    ($this->distribute)(OrderingWorld::GROWER);

    expect(($this->settled)())->toBeFalse();
})->group('S29');

it('reopens only a distributed producer order (R18)', function () {
    ($this->bakerSent)();

    expect(fn () => ($this->reopen)())->toThrow(ProducerNotDistributed::class)
        ->and(fn () => ($this->reopenProducerOrder)($this->world->manager, '00000000-0000-7000-8000-0000000000ff', OrderingWorld::BAKER))->toThrow(CycleNotFound::class)
        ->and(fn () => ($this->reopen)('00000000-0000-7000-8000-0000000000b9'))->toThrow(ProducerNotInCycle::class);
})->group('R18');

it('does not reopen a producer once the cycle is settled, which a later task adds (R18, T30)', function () {
    ($this->onlyBaker)();
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();

    expect(fn () => ($this->reopen)())->toThrow(CycleAlreadySettled::class)
        ->and(($this->reception)()->distributed)->toBeTrue();
})->group('R18');

// R12a, D10 — SendEmptyProducerOrders validates, distributes and settles

it('validates and distributes a producer sent because it has no line to send (R12a, D10)', function () {
    ($this->order)('A', $this->bread, '4');
    ($this->order)('A', $this->apples, '2');
    ($this->send)();
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);
    $this->world->catalog->deactivate($this->apples);

    ($this->world->sendEmptyProducerOrders)();

    $grower = ($this->reception)(OrderingWorld::GROWER);
    expect([$grower->validated, $grower->distributed])->toBe([true, true])
        ->and(($this->settled)())->toBeFalse();
})->group('R12a');

it('settles the cycle through the same step when the empty producer is the last one (R12a, D10, R18)', function () {
    ($this->order)('A', $this->bread, '4');
    ($this->order)('A', $this->apples, '2');
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();
    $this->world->catalog->deactivate($this->apples);
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);

    ($this->world->sendEmptyProducerOrders)();

    expect(($this->settled)())->toBeTrue()
        ->and(($this->dropped)('A'))->toBe([$this->apples])
        ->and(($this->pickup)('A'))->toBe(PickupStatus::Pending);
})->group('R12a', 'S28');

it('does not distribute an empty producer while another order is still waiting (R12a, D10)', function () {
    ($this->order)('A', $this->bread, '4');
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);

    ($this->world->sendEmptyProducerOrders)();

    expect(($this->reception)(OrderingWorld::GROWER)->distributed)->toBeTrue()
        ->and(fn () => ($this->reception)(OrderingWorld::BAKER))->toThrow(ProducerOrderNotSent::class)
        ->and(($this->settled)())->toBeFalse();
})->group('R12a');

it('never settles a cycle without a participating producer (R12a)', function () {
    ($this->world->stopParticipating)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->stopParticipating)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);

    ($this->world->sendEmptyProducerOrders)();

    expect(($this->settled)())->toBeFalse();
})->group('R12a');

it('settles a cycle whose every producer is empty, once, when the last is sent (R12a, D10)', function () {
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);

    ($this->world->sendEmptyProducerOrders)();
    ($this->world->sendEmptyProducerOrders)();

    expect(($this->settled)())->toBeTrue();
})->group('R12a');
