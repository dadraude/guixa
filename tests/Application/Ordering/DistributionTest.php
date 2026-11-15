<?php

use Guixa\Ordering\Application\CloseCycleAfterBasketValidation;
use Guixa\Ordering\Application\CycleSettlement;
use Guixa\Ordering\Application\MarkProducerDistributed;
use Guixa\Ordering\Application\ReopenProducerOrder;
use Guixa\Ordering\Application\ValidateReception;
use Guixa\Ordering\Domain\Error\CycleClosed;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\CycleSettled;
use Guixa\Ordering\Domain\Error\PickupNotPending;
use Guixa\Ordering\Domain\Error\ProducerDistributed;
use Guixa\Ordering\Domain\Error\ProducerNotDistributed;
use Guixa\Ordering\Domain\Error\ProducerNotInCycle;
use Guixa\Ordering\Domain\Error\ProducerOrderNotSent;
use Guixa\Ordering\Domain\Error\ReceptionNotValidated;
use Guixa\Ordering\Domain\Error\ReceptionUnbalanced;
use Guixa\Ordering\Domain\Error\RedistributionBlockedByValidatedBasket;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Ordering\Domain\WeeklyBasketStates;
use Guixa\Shared\Application\Actor;
use Illuminate\Auth\Access\AuthorizationException;
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
        'A' => Actor::memberOf('00000000-0000-7000-8000-0000000000a1', DIST_A),
        'B' => Actor::memberOf('00000000-0000-7000-8000-0000000000a2', DIST_B),
        'C' => Actor::memberOf('00000000-0000-7000-8000-0000000000a3', DIST_C),
    ];
    $this->householdIds = ['A' => DIST_A, 'B' => DIST_B, 'C' => DIST_C];

    $transactions = new ImmediateTransactionRunner;
    $loader = new ReceptionLoader($this->world->orders, $this->world->catalog, $this->world->availability, $this->world->receivedTotals, $this->world->baskets);
    $settlement = new CycleSettlement($this->world->orders, new NotDeliveredMarks($this->world->catalog, $this->world->availability, $this->world->orders), $this->world->baskets);
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
    // The state of the household's weekly base basket in Sales: null before settlement or without an order.
    $this->pickup = fn (string $household) => $this->world->baskets->stateOfHousehold($this->cycleId, $this->householdIds[$household]);
    $orderOf = fn (string $household) => $this->world->orders->ofHousehold($this->cycleId, $this->householdIds[$household]);
    // Sales validated the household's base basket: from now on it is financially frozen.
    $this->freeze = fn (string $household) => $this->world->baskets->validate($orderOf($household)->id);
    $this->setActual = fn (string $household, string $productId, string $quantity) => $this->world->baskets->setActual($orderOf($household)->id, $productId, $quantity);
    $this->lates = fn (string $household) => $this->world->baskets->latesOfHousehold($this->cycleId, $this->householdIds[$household]);
    $this->closable = fn () => WeeklyBasketStates::areFinished($this->world->baskets->snapshot($this->cycleId)->states());
    $this->actual = fn (string $household, string $productId) => $this->world->baskets->snapshot($this->cycleId)->effective($this->householdIds[$household], $productId);
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

    expect(fn () => ($this->validate)(OrderingWorld::BAKER, $this->households['A']))->toThrow(AuthorizationException::class)
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

    expect(fn () => ($this->distribute)(OrderingWorld::BAKER, $this->households['A']))->toThrow(AuthorizationException::class);

    ($this->distribute)();

    expect(fn () => ($this->reopen)(OrderingWorld::BAKER, $this->households['A']))->toThrow(AuthorizationException::class);
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

it('makes every household with an allocation have one weekly base basket once settled (R19, R24, S27)', function () {
    ($this->onlyBaker)();
    ($this->bakerSent)();
    ($this->validate)();

    expect(($this->pickup)('A'))->toBeNull();

    ($this->distribute)();

    expect(($this->settled)())->toBeTrue()
        ->and([($this->pickup)('A'), ($this->pickup)('B'), ($this->pickup)('C')])->toBe(['draft', 'draft', 'draft']);
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
        ->and(($this->pickup)('A'))->toBe('draft');
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

    expect(($this->pickup)('A'))->toBe('draft')
        ->and(($this->pickup)('B'))->toBe('nothing_to_collect');
})->group('S20', 'R24');

it('closes a pickup whose only lines were dropped as nothing to collect (R18, R24, S28)', function () {
    ($this->order)('A', $this->bread, '4');
    ($this->order)('B', $this->apples, '2');
    ($this->world->stopParticipating)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);
    ($this->send)();
    ($this->validate)();

    ($this->distribute)();

    expect(($this->pickup)('B'))->toBe('nothing_to_collect');
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

it('revives a nothing-to-collect base and gives a validated household a late weekly basket when a product received as 0 arrives (R18, R19, R24a, S30)', function () {
    ($this->onlyBaker)();
    ($this->order)('A', $this->bread, '4');
    ($this->order)('A', $this->rye, '1');
    ($this->order)('B', $this->rye, '1');
    ($this->order)('C', $this->bread, '10');
    ($this->send)();
    ($this->setReceived)($this->rye, '0');
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');
    expect(($this->pickup)('B'))->toBe('nothing_to_collect');

    ($this->reopen)();
    ($this->setReceived)($this->rye, '2');
    ($this->validate)();
    ($this->distribute)();

    expect(($this->pickup)('B'))->toBe('draft')
        ->and(($this->actual)('B', $this->rye))->toBe(1000)
        ->and(($this->pickup)('A'))->toBe('validated')
        ->and(($this->actual)('A', $this->bread))->toBe(4000)
        ->and(collect(($this->lates)('A'))->map(fn (array $late) => [$late['state'], $late['effective']])->all())->toBe([['draft', [$this->rye => 1000]]])
        ->and(($this->actual)('A', $this->rye))->toBe(1000)
        ->and(($this->closable)())->toBeFalse();

    ($this->freeze)('B');
    ($this->freeze)('C');
    expect(($this->closable)())->toBeFalse();

    $this->world->baskets->validateLate(($this->world->orders->ofHousehold($this->cycleId, DIST_A))->id);
    expect(($this->lates)('A')[0]['state'])->toBe('validated')
        ->and(($this->closable)())->toBeTrue();
})->group('R18', 'R19', 'R24', 'S30');

it('keeps received and allocated balanced after a rejected correction of a late arrival, so the reception can still be distributed (R17, R18, S30, S63)', function () {
    ($this->onlyBaker)();
    ($this->order)('A', $this->bread, '4');
    ($this->order)('A', $this->rye, '1');
    ($this->order)('B', $this->rye, '1');
    ($this->order)('C', $this->bread, '10');
    ($this->send)();
    ($this->setReceived)($this->rye, '0');
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');
    ($this->reopen)();
    ($this->setReceived)($this->rye, '2');

    expect(fn () => ($this->setReceived)($this->rye, '3'))->toThrow(RedistributionBlockedByValidatedBasket::class);

    $rye = collect(($this->reception)()->products)->firstWhere('productId', $this->rye);
    expect($rye->received)->toBe('2.000')
        ->and($rye->balancedForDistribution)->toBeTrue()
        ->and(($this->lates)('A')[0]['effective'])->toBe([$this->rye => 1000]);

    ($this->validate)();
    ($this->distribute)();
    expect(($this->reception)()->distributed)->toBeTrue();
})->group('R17', 'R18', 'S30', 'S63');

it('adds a second late arrival to the household open late draft instead of creating another basket (R18, R19, S91)', function () {
    ($this->onlyBaker)();
    $cake = $this->world->catalog->offer(OrderingWorld::BAKER, 'Cake', 'loaf', 500, variable: false);
    ($this->order)('A', $this->bread, '4');
    ($this->order)('A', $this->rye, '1');
    ($this->order)('A', $cake, '2');
    ($this->order)('C', $this->bread, '10');
    ($this->send)();
    ($this->setReceived)($this->rye, '0');
    ($this->setReceived)($cake, '0');
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');

    ($this->reopen)();
    ($this->setReceived)($this->rye, '1');
    ($this->setReceived)($cake, '2');

    expect(($this->lates)('A'))->toHaveCount(1)
        ->and(($this->lates)('A')[0]['effective'])->toBe([$this->rye => 1000, $cake => 2000])
        ->and(($this->pickup)('A'))->toBe('validated');
})->group('R18', 'R19', 'S91');

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
        ->and(($this->pickup)('A'))->toBe('draft');
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

it('resets a staff actual quantity when reception allocations change by either way, leaving unchanged lines alone (R18, D16)', function (bool $byHand) {
    ($this->onlyBaker)();
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();
    ($this->setActual)('A', $this->bread, '5');
    ($this->setActual)('B', $this->bread, '4');
    ($this->reopen)();

    if ($byHand) {
        ($this->allocate)($this->bread, 'A', '3');
    } else {
        ($this->setReceived)($this->bread, '15');
    }

    // A's allocation changed (4 to 3): its override is gone. B (6 to 5) resets only in the proportional case.
    expect(($this->actual)('A', $this->bread))->toBe(3000)
        ->and(($this->actual)('B', $this->bread))->toBe($byHand ? 4000 : 5000);
})->with([true, false])->group('R18', 'D16');

it('rejects changing the allocation of a product a validated basket has a line of (R18, R23)', function () {
    ($this->onlyBaker)();
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');
    ($this->reopen)();

    expect(fn () => ($this->allocate)($this->bread, 'A', '3'))->toThrow(RedistributionBlockedByValidatedBasket::class);
})->group('R18', 'R23');

it('lets two fully validated households split an unordered product into two late drafts and then correct each, including to zero (R14, R16, R18, R19, S40, S91)', function () {
    ($this->onlyBaker)();
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');
    ($this->freeze)('B');
    ($this->reopen)();
    ($this->world->addUnorderedProduct)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->rye, '2');

    ($this->allocate)($this->rye, 'A', '1');
    ($this->allocate)($this->rye, 'B', '1');
    $drafts = fn (string $household) => collect(($this->lates)($household))->map(fn (array $late) => [$late['state'], $late['effective']])->all();
    expect($drafts('A'))->toBe([['draft', [$this->rye => 1000]]])
        ->and($drafts('B'))->toBe([['draft', [$this->rye => 1000]]]);

    ($this->allocate)($this->rye, 'A', '2');
    ($this->allocate)($this->rye, 'B', '0');

    expect($drafts('A'))->toBe([['draft', [$this->rye => 2000]]])
        ->and($drafts('B'))->toBe([['nothing_to_collect', [$this->rye => 0]]])
        ->and(($this->pickup)('A'))->toBe('validated')
        ->and(($this->pickup)('B'))->toBe('validated')
        ->and(($this->actual)('A', $this->bread))->toBe(4000)
        ->and(($this->actual)('B', $this->bread))->toBe(6000)
        ->and(collect(($this->reception)()->products)->firstWhere('productId', $this->rye)->balancedForDistribution)->toBeTrue();

    ($this->validate)();
    ($this->distribute)();
    expect(($this->reception)()->distributed)->toBeTrue();
})->group('R14', 'R16', 'R18', 'R19', 'S40', 'S91');

it('lets a replaced received total reset a hand allocation that went to a late draft, to zero (R15, R16, R18, R19)', function () {
    ($this->onlyBaker)();
    ($this->order)('A', $this->bread, '4');
    ($this->order)('B', $this->rye, '2');
    ($this->send)();
    ($this->setReceived)($this->rye, '0');
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');
    ($this->reopen)();
    ($this->setReceived)($this->rye, '2');
    ($this->allocate)($this->rye, 'B', '1');
    ($this->allocate)($this->rye, 'A', '1');
    expect(($this->lates)('A')[0]['effective'])->toBe([$this->rye => 1000]);

    ($this->world->setReceivedTotal)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->rye, '2', overwriteManual: true);

    expect(($this->lates)('A')[0]['state'])->toBe('nothing_to_collect')
        ->and(($this->lates)('A')[0]['effective'])->toBe([$this->rye => 0])
        ->and(($this->actual)('B', $this->rye))->toBe(2000)
        ->and(collect(($this->reception)()->products)->firstWhere('productId', $this->rye)->balancedForDistribution)->toBeTrue();
})->group('R15', 'R16', 'R18', 'R19');

it('gives a fully validated household a late draft for a product only another household ordered, once its total arrived (R16, R18, R19, S30)', function () {
    ($this->onlyBaker)();
    ($this->order)('A', $this->bread, '4');
    ($this->order)('B', $this->rye, '2');
    ($this->send)();
    ($this->setReceived)($this->rye, '0');
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');
    expect(($this->pickup)('B'))->toBe('nothing_to_collect');

    ($this->reopen)();
    ($this->setReceived)($this->rye, '2');
    expect(($this->pickup)('B'))->toBe('draft');
    ($this->allocate)($this->rye, 'B', '1');
    ($this->allocate)($this->rye, 'A', '1');

    expect(($this->actual)('B', $this->rye))->toBe(1000)
        ->and(collect(($this->lates)('A'))->map(fn (array $late) => [$late['state'], $late['effective']])->all())->toBe([['draft', [$this->rye => 1000]]])
        ->and(($this->pickup)('A'))->toBe('validated')
        ->and(collect(($this->reception)()->products)->firstWhere('productId', $this->rye)->balancedForDistribution)->toBeTrue();
})->group('R16', 'R18', 'R19', 'S30');

it('still rejects a hand allocation of a product a validated basket has a line of, even at zero, and changes nothing (R16, R18, R23)', function () {
    ($this->onlyBaker)();
    ($this->order)('A', $this->bread, '4');
    ($this->order)('A', $this->rye, '1');
    ($this->order)('B', $this->rye, '1');
    ($this->send)();
    ($this->setReceived)($this->rye, '0');
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');
    ($this->reopen)();
    ($this->setReceived)($this->rye, '2');
    $before = [($this->lates)('A'), ($this->actual)('B', $this->rye), ($this->pickup)('A')];

    expect(fn () => ($this->allocate)($this->rye, 'A', '1'))->toThrow(RedistributionBlockedByValidatedBasket::class)
        ->and(fn () => ($this->allocate)($this->rye, 'B', '2'))->toThrow(RedistributionBlockedByValidatedBasket::class)
        ->and([($this->lates)('A'), ($this->actual)('B', $this->rye), ($this->pickup)('A')])->toBe($before);
})->group('R16', 'R18', 'R23');

it('gives a household whose baskets are all validated a late weekly basket when a product no basket had is allocated to it by hand, and adds the next one to that draft (R14, R16, R18, R19, S40, S91)', function () {
    ($this->onlyBaker)();
    $cake = $this->world->catalog->offer(OrderingWorld::BAKER, 'Cake', 'loaf', 500, variable: false);
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');
    ($this->freeze)('B');
    ($this->freeze)('C');
    ($this->reopen)();
    ($this->world->addUnorderedProduct)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->rye, '1');
    ($this->world->addUnorderedProduct)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $cake, '2');

    ($this->allocate)($this->rye, 'A', '1');
    expect(($this->closable)())->toBeFalse();
    ($this->allocate)($cake, 'A', '2');

    expect(($this->pickup)('A'))->toBe('validated')
        ->and(($this->actual)('A', $this->bread))->toBe(4000)
        ->and(collect(($this->lates)('A'))->map(fn (array $late) => [$late['state'], $late['effective']])->all())
        ->toBe([['draft', [$this->rye => 1000, $cake => 2000]]])
        ->and(($this->lates)('B'))->toBe([])
        ->and(($this->closable)())->toBeFalse();

    ($this->validate)();
    ($this->distribute)();
    expect(($this->reception)()->distributed)->toBeTrue();
    $this->world->baskets->validateLate($this->world->orders->ofHousehold($this->cycleId, DIST_A)->id);
    expect(($this->closable)())->toBeTrue();
})->group('R14', 'R16', 'R18', 'R19', 'S40', 'S91');

it('closes at settlement when every pickup has nothing to collect (R24b, S20, S46)', function () {
    ($this->onlyBaker)();
    ($this->order)('A', $this->bread, '4');
    ($this->send)();
    ($this->setReceived)($this->bread, '0');
    ($this->validate)();
    ($this->distribute)();

    expect(fn () => ($this->reopen)())->toThrow(CycleClosed::class);
})->group('R24b', 'S20', 'S46');

it('redistributes while leaving an actual-versus-allocation difference flagged (R18, S61)', function () {
    ($this->onlyBaker)();
    $beef = $this->world->catalog->offer(OrderingWorld::BAKER, 'Beef', 'kg', 2000, variable: true);
    ($this->order)('A', $beef, '1');
    ($this->order)('B', $this->bread, '1');
    ($this->send)();
    ($this->validate)();
    ($this->distribute)();
    ($this->setActual)('A', $beef, '1.120');
    ($this->freeze)('A');

    ($this->reopen)();
    ($this->distribute)();

    $reception = ($this->reception)();
    $product = collect($reception->products)->firstWhere('productId', $beef);
    expect($reception->distributed)->toBeTrue()->and($product->difference)->toBe('-0.120')
        ->and($product->hasAdjustments)->toBeTrue();
})->group('R18', 'S61');

it('rejects redistributing a product a validated basket contains, however it was collected, and changes nothing (R18, R23, S63, S89)', function (bool $collected) {
    ($this->onlyBaker)();
    $beef = $this->world->catalog->offer(OrderingWorld::BAKER, 'Beef', 'kg', 2000, variable: true);
    ($this->order)('A', $beef, '1');
    ($this->order)('B', $beef, '1');
    ($this->send)();
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');
    if ($collected) {
        $this->world->baskets->collect(($this->world->orders->ofHousehold($this->cycleId, DIST_A))->id);
    }
    ($this->reopen)();

    expect(fn () => ($this->setReceived)($beef, '3'))->toThrow(RedistributionBlockedByValidatedBasket::class);

    $product = collect(($this->reception)()->products)->firstWhere('productId', $beef);
    $allocations = collect($product->allocations)->keyBy('householdName');
    expect($product->received)->toBe('2.000')
        ->and($allocations['Household A']->allocated)->toBe('1.000')
        ->and($allocations['Household B']->allocated)->toBe('1.000')
        ->and(($this->actual)('B', $beef))->toBe(1000)
        ->and(($this->pickup)('A'))->toBe($collected ? 'collected' : 'validated')
        ->and(($this->pickup)('B'))->toBe('draft')
        ->and(($this->lates)('A'))->toBe([]);
})->with([false, true])->group('R18', 'R23', 'S63', 'S89');

it('rejects a hand-set allocation of a product another household\'s validated basket contains, in both branches, and changes nothing (R16, R18, R23, S63, S64)', function (bool $line) {
    ($this->onlyBaker)();
    $beef = $this->world->catalog->offer(OrderingWorld::BAKER, 'Beef', 'kg', 2000, variable: true);
    ($this->order)('A', $beef, '1');
    ($this->order)('B', $beef, '1');
    ($this->order)('C', $this->bread, '1');
    ($this->send)();
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');
    ($this->reopen)();
    $observed = fn () => [
        collect(($this->reception)()->products)->map(fn ($product) => [$product->productId, $product->received, collect($product->allocations)->map(fn ($a) => [$a->householdId, $a->allocated])->all()])->all(),
        ($this->pickup)('A'), ($this->pickup)('B'), ($this->actual)('B', $beef), ($this->lates)('A'),
    ];
    $before = $observed();

    // B has a beef line (an update); C has none (a new line).
    expect(fn () => ($this->allocate)($beef, $line ? 'B' : 'C', '2'))->toThrow(RedistributionBlockedByValidatedBasket::class);

    expect($observed())->toBe($before);
})->with([true, false])->group('R16', 'R18', 'R23', 'S63', 'S64');

it('redistributes a product no validated basket contains into the drafts, replacing the actual override (R18, R19a, S64)', function () {
    ($this->onlyBaker)();
    ($this->order)('A', $this->rye, '1');
    ($this->order)('B', $this->bread, '2');
    ($this->order)('C', $this->bread, '3');
    ($this->send)();
    ($this->validate)();
    ($this->distribute)();
    ($this->setActual)('B', $this->bread, '1');
    ($this->freeze)('A');
    ($this->reopen)();

    ($this->allocate)($this->bread, 'B', '3');
    ($this->allocate)($this->bread, 'C', '2');

    expect(($this->actual)('B', $this->bread))->toBe(3000)
        ->and(($this->actual)('C', $this->bread))->toBe(2000)
        ->and(($this->pickup)('A'))->toBe('validated')
        ->and(($this->lates)('A'))->toBe([]);
})->group('R18', 'S64');

it('gives a newly allocated household a draft base after settlement (R18, R19)', function () {
    ($this->onlyBaker)();
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();
    ($this->reopen)();
    $household = '00000000-0000-7000-8000-0000000000e4';
    $this->world->households->know($household, 'Household D');
    ($this->world->addUnorderedProduct)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->rye, '1');
    ($this->world->setAllocation)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->rye, $household, '1');
    ($this->validate)();
    ($this->distribute)();

    expect($this->world->baskets->stateOfHousehold($this->cycleId, $household))->toBe('draft');
})->group('R18', 'R19');

it('rejects changing the received total of an unordered product a validated basket has a line of, and keeps the old total (R14, R18, R23)', function () {
    ($this->onlyBaker)();
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();
    ($this->reopen)();
    $household = '00000000-0000-7000-8000-0000000000e4';
    $this->world->households->know($household, 'Household D');
    ($this->world->addUnorderedProduct)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->rye, '1');
    ($this->world->setAllocation)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->rye, $household, '1');
    ($this->validate)();
    ($this->distribute)();
    $this->world->baskets->validate($this->world->orders->ofHousehold($this->cycleId, $household)->id);
    ($this->reopen)();

    expect(fn () => ($this->world->addUnorderedProduct)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->rye, '2'))
        ->toThrow(RedistributionBlockedByValidatedBasket::class)
        ->and(collect(($this->reception)()->products)->firstWhere('productId', $this->rye)->received)->toBe('1.000');
})->group('R14', 'R18', 'R23');

it('keeps a marked line of a validated basket in the order while the cycle stays open (R23)', function () {
    ($this->onlyBaker)();
    ($this->order)('A', $this->rye, '1');
    $this->world->catalog->deactivate($this->rye);
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');

    expect(fn () => ($this->world->removeMarkedLine)($this->households['A'], $this->cycleId, $this->rye))->toThrow(PickupNotPending::class);
})->group('R23');

it('closes the cycle when the last draft is validated while validated baskets stay pending collection, then rejects reopening (R23a, R24b, S54, S83)', function () {
    ($this->onlyBaker)();
    ($this->order)('A', $this->bread, '4');
    ($this->order)('B', $this->bread, '4');
    ($this->order)('C', $this->rye, '1');
    ($this->send)();
    ($this->setReceived)($this->rye, '0');
    ($this->validate)();
    ($this->distribute)();
    $close = fn () => (new CloseCycleAfterBasketValidation($this->world->cycles, $this->world->baskets, $this->world->clock))($this->cycleId);
    $isClosed = fn () => $this->world->cycles->get($this->cycleId)->isClosed();

    ($this->freeze)('A');
    $close();
    expect(($this->pickup)('C'))->toBe('nothing_to_collect')
        ->and($isClosed())->toBeFalse();

    ($this->freeze)('B');
    $close();
    expect($isClosed())->toBeTrue()
        ->and([($this->pickup)('A'), ($this->pickup)('B')])->toBe(['validated', 'validated'])
        ->and(fn () => ($this->reopen)())->toThrow(CycleClosed::class);

    $this->world->baskets->collect($this->world->orders->ofHousehold($this->cycleId, DIST_A)->id);
    expect(($this->pickup)('A'))->toBe('collected')
        ->and(($this->pickup)('B'))->toBe('validated')
        ->and($isClosed())->toBeTrue();
})->group('R23a', 'R24b', 'S54', 'S83');

it('keeps the next cycle editable while the previous cycle is settled with a validated basket pending collection (R4, S9, S26)', function () {
    ($this->onlyBaker)();
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();
    ($this->freeze)('A');
    $next = ($this->world->openCycle)($this->world->manager, '2026-11-14', [OrderingWorld::BAKER]);

    ($this->world->setOrderLine)($this->households['A'], $next, $this->bread, '2');
    ($this->world->setOrderLine)($this->households['A'], $next, $this->bread, '3');

    $order = $this->world->orders->ofHousehold($next, DIST_A);
    expect(($this->pickup)('A'))->toBe('validated')
        ->and($this->world->cycles->get($next)->isSettled())->toBeFalse()
        ->and($order->lines())->toHaveCount(1)
        ->and($order->lines()[0]->ordered->toString())->toBe('3.000');
})->group('R4', 'S9', 'S26');

it('adds a producer to a cycle until it is settled, then refuses it (R4a, S51)', function () {
    $early = '00000000-0000-7000-8000-0000000000b8';
    $late = '00000000-0000-7000-8000-0000000000b9';
    $this->world->rules->activate($early, 3, '20:00');
    $this->world->rules->activate($late, 3, '20:00');
    ($this->onlyBaker)();

    ($this->world->addParticipant)($this->world->manager, $this->cycleId, $early);
    expect(($this->settled)())->toBeFalse();

    ($this->world->stopParticipating)($this->world->manager, $this->cycleId, $early);
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();

    expect(($this->settled)())->toBeTrue()
        ->and(fn () => ($this->world->addParticipant)($this->world->manager, $this->cycleId, $late))->toThrow(CycleSettled::class);
})->group('R4a', 'S51');

it('lets a stopped producer participate again until the cycle is settled, then refuses it (R4a, S51)', function () {
    ($this->onlyBaker)();
    $again = fn () => ($this->world->participateAgain)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);

    $again();
    ($this->onlyBaker)();
    ($this->bakerSent)();
    ($this->validate)();
    ($this->distribute)();

    expect(($this->settled)())->toBeTrue()
        ->and($again)->toThrow(CycleSettled::class);
})->group('R4a', 'S51');
