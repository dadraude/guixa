<?php

use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\Ordering\OrderingWorld;

const SUMMARY_HOUSEHOLD_A = '00000000-0000-7000-8000-0000000000e1';
const SUMMARY_HOUSEHOLD_B = '00000000-0000-7000-8000-0000000000e2';

function summaryMember(string $accountId, string $householdId): Actor
{
    return new Actor($accountId, [Role::Member], $householdId);
}

beforeEach(function () {
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->world->households->know(SUMMARY_HOUSEHOLD_A, 'Household A');
    $this->world->households->know(SUMMARY_HOUSEHOLD_B, 'Household B');
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Loaf', 'loaf', 350, variable: false);
    $this->apples = $this->world->catalog->offer(OrderingWorld::GROWER, 'Apples', 'kg', 280, variable: true);
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER]);
    $this->a = summaryMember('00000000-0000-7000-8000-0000000000a1', SUMMARY_HOUSEHOLD_A);
    $this->b = summaryMember('00000000-0000-7000-8000-0000000000a2', SUMMARY_HOUSEHOLD_B);
    $this->set = fn (Actor $actor, string $productId, string $quantity) => ($this->world->setOrderLine)($actor, $this->cycleId, $productId, $quantity);
    $this->summary = fn () => ($this->world->getCycleSummary)($this->world->manager, $this->cycleId);
});

it('totals a product and breaks it down per household (R13, S52)', function () {
    ($this->set)($this->a, $this->bread, '2');
    ($this->set)($this->b, $this->bread, '3');

    $summary = ($this->summary)();
    $baker = collect($summary->producers)->firstWhere('producerId', OrderingWorld::BAKER);

    expect($summary->cycleId)->toBe($this->cycleId)
        ->and($summary->pickupDate)->toBe('2026-11-07')
        ->and($baker->products)->toHaveCount(1)
        ->and($baker->products[0]->name)->toBe('Loaf')
        ->and($baker->products[0]->unit)->toBe('loaf')
        ->and($baker->products[0]->quantity)->toBe('5.000')
        ->and(collect($baker->products[0]->households)->map(fn ($h) => [$h->householdName, $h->quantity])->all())
        ->toBe([['Household A', '2.000'], ['Household B', '3.000']]);
});

it('groups the products under their producer and leaves out producers nobody ordered from (R13)', function () {
    ($this->set)($this->a, $this->apples, '1.500');
    ($this->set)($this->b, $this->apples, '0.250');

    $producers = ($this->summary)()->producers;

    expect($producers)->toHaveCount(1)
        ->and($producers[0]->producerId)->toBe(OrderingWorld::GROWER)
        ->and($producers[0]->products[0]->quantity)->toBe('1.750');
});

it('sums the lines of one product only, not other products of the same household (R13)', function () {
    $roll = $this->world->catalog->offer(OrderingWorld::BAKER, 'Roll', 'roll', 120, variable: false);
    ($this->set)($this->a, $this->bread, '2');
    ($this->set)($this->a, $roll, '4');
    ($this->set)($this->b, $roll, '1');

    $products = collect(($this->summary)()->producers[0]->products)->keyBy('name');

    expect($products['Loaf']->quantity)->toBe('2.000')
        ->and($products['Roll']->quantity)->toBe('5.000');
});

it('shows an empty summary for a cycle with no orders (R13)', function () {
    expect(($this->summary)()->producers)->toBe([]);
});

it('keeps an order without lines out of the summary (R13)', function () {
    ($this->set)($this->a, $this->bread, '2');
    ($this->set)($this->a, $this->bread, '0');

    expect(($this->summary)()->producers)->toBe([]);
});

it('names a household Membership no longer knows by its id (R13)', function () {
    $unknown = '00000000-0000-7000-8000-0000000000e9';
    ($this->set)(summaryMember('00000000-0000-7000-8000-0000000000a9', $unknown), $this->bread, '1');

    expect(($this->summary)()->producers[0]->products[0]->households[0]->householdName)->toBe($unknown);
});

it('is for managers and an existing cycle (R13)', function () {
    expect(fn () => ($this->world->getCycleSummary)($this->a, $this->cycleId))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->getCycleSummary)($this->world->manager, '00000000-0000-7000-8000-0000000000ff'))
        ->toThrow(CycleNotFound::class);
});
