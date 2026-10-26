<?php

use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\Ordering\OrderingWorld;

const PRODUCER_SUMMARY_ANNA = '00000000-0000-7000-8000-0000000000a1';
const PRODUCER_SUMMARY_BIEL = '00000000-0000-7000-8000-0000000000a2';
const PRODUCER_SUMMARY_HOUSEHOLD_A = '00000000-0000-7000-8000-0000000000e1';
const PRODUCER_SUMMARY_HOUSEHOLD_B = '00000000-0000-7000-8000-0000000000e2';

beforeEach(function () {
    // Thursday 22 October 2026; the pickup is Saturday 7 November and the baker's cutoff Wednesday 4 November 20:00.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->world->households->know(PRODUCER_SUMMARY_HOUSEHOLD_A, 'Household A');
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Loaf', 'loaf', 350, variable: false);
    $this->jam = $this->world->catalog->offer(OrderingWorld::BAKER, 'Jam', 'jar', 400, variable: false);
    $this->apples = $this->world->catalog->offer(OrderingWorld::GROWER, 'Apples', 'kg', 300, variable: true);
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER]);
    $this->anna = Actor::memberOf(PRODUCER_SUMMARY_ANNA, PRODUCER_SUMMARY_HOUSEHOLD_A);
    $this->biel = Actor::memberOf(PRODUCER_SUMMARY_BIEL, PRODUCER_SUMMARY_HOUSEHOLD_B);
    $this->baker = Actor::producer('00000000-0000-7000-8000-0000000000aa', OrderingWorld::BAKER);
    $this->grower = Actor::producer('00000000-0000-7000-8000-0000000000ab', OrderingWorld::GROWER);
    $this->order = fn (Actor $member, string $productId, string $quantity) => ($this->world->setOrderLine)($member, $this->cycleId, $productId, $quantity);
    $this->closeBaker = fn () => ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    $this->sendBaker = fn () => ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    $this->summary = fn (?Actor $actor = null) => ($this->world->getProducerCycleSummary)($actor ?? $this->baker, $this->cycleId);
    $this->byName = fn (?Actor $actor = null) => collect(($this->summary)($actor)->products)->keyBy('name');
});

// S7 — Producer privacy

it('shows the total quantity per product once the order is sent, without household names or other producers (R12, S7)', function () {
    ($this->order)($this->anna, $this->bread, '15');
    ($this->order)($this->biel, $this->bread, '25');
    ($this->order)($this->biel, $this->apples, '4');
    ($this->closeBaker)();
    ($this->sendBaker)();

    $summary = ($this->summary)();

    expect($summary->sent)->toBeTrue()
        ->and($summary->cycleId)->toBe($this->cycleId)
        ->and($summary->pickupDate)->toBe('2026-11-07')
        ->and(collect($summary->products)->map(fn ($p) => [$p->name, $p->unit, $p->quantity])->all())->toBe([['Loaf', 'loaf', '40.000']])
        ->and(json_encode($summary))->not->toContain('Household')->not->toContain(PRODUCER_SUMMARY_HOUSEHOLD_A)->not->toContain('Apples');
});

it('leaves the lines marked will not be delivered out of the sent totals (R3b, R12a)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->order)($this->anna, $this->jam, '1');
    ($this->world->setProductAvailability)($this->baker, $this->cycleId, $this->jam, false);
    ($this->closeBaker)();
    ($this->sendBaker)();

    expect(collect(($this->summary)()->products)->pluck('name')->all())->toBe(['Loaf']);
});

it('keeps a sent order unchanged when a product is deactivated afterwards (R3a, S50)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->closeBaker)();
    ($this->sendBaker)();
    $this->world->catalog->deactivate($this->bread);

    expect(($this->byName)()['Loaf']->quantity)->toBe('2.000');
});

it('is for the producer of the cycle only, not for another producer, a manager or a member (R2, S7)', function () {
    $other = Actor::producer('00000000-0000-7000-8000-0000000000ac', '00000000-0000-7000-8000-0000000000b9');

    expect(fn () => ($this->summary)($this->world->manager))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->summary)($this->anna))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->summary)(new Actor('00000000-0000-7000-8000-0000000000ad', [Role::Producer])))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->summary)($other))->toThrow(CycleNotFound::class);
});

it('answers not found for an unknown cycle (R12)', function () {
    expect(fn () => ($this->world->getProducerCycleSummary)($this->baker, '00000000-0000-7000-8000-0000000000ff'))->toThrow(CycleNotFound::class);
});

// S8 — No aggregate before sending

it('shows no quantities while the order is not sent, even once the cutoff has passed (R12, S8)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->closeBaker)();

    $summary = ($this->summary)();

    expect($summary->sent)->toBeFalse()
        ->and(collect($summary->products)->pluck('quantity')->unique()->all())->toBe([null]);
});

it('shows no quantities before the cutoff either (R12)', function () {
    ($this->order)($this->anna, $this->bread, '2');

    expect(collect(($this->summary)()->products)->pluck('quantity')->unique()->all())->toBe([null]);
});

// Availability per product (R3c)

it('lists the own orderable products by name with whether each is available in the cycle (R3c)', function () {
    ($this->world->setProductAvailability)($this->baker, $this->cycleId, $this->jam, false);

    $summary = ($this->summary)();

    expect(collect($summary->products)->map(fn ($p) => [$p->name, $p->available])->all())->toBe([['Jam', false], ['Loaf', true]]);
});

it('shows a cycle where the producer does not take part as not found (R2)', function () {
    $otherCycle = ($this->world->openCycle)($this->world->manager, '2026-11-14', [OrderingWorld::GROWER]);

    expect(fn () => ($this->world->getProducerCycleSummary)($this->baker, $otherCycle))->toThrow(CycleNotFound::class);
});

it('shows a cancelled cycle as not found, since there is nothing to see (R4b)', function () {
    ($this->world->cancelCycle)($this->world->manager, $this->cycleId);

    expect(fn () => ($this->summary)())->toThrow(CycleNotFound::class);
});
