<?php

use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\FractionalQuantityNotAllowed;
use Guixa\Ordering\Domain\Error\NoHousehold;
use Guixa\Ordering\Domain\Error\ProducerClosed;
use Guixa\Ordering\Domain\Error\ProducerNotParticipating;
use Guixa\Ordering\Domain\Error\ProductNotOrderable;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\InvalidQuantity;
use Guixa\Shared\Domain\Role;
use Tests\Support\Ordering\OrderingWorld;

const ANNA = '00000000-0000-7000-8000-0000000000a1';
const BIEL = '00000000-0000-7000-8000-0000000000a2';
const HOUSEHOLD_H = '00000000-0000-7000-8000-0000000000e1';
const HOUSEHOLD_J = '00000000-0000-7000-8000-0000000000e2';

function memberActor(string $accountId, string $householdId = HOUSEHOLD_H): Actor
{
    return new Actor($accountId, [Role::Member], $householdId);
}

beforeEach(function () {
    // Thursday 22 October 2026; pickup is Saturday 7 November, so the baker closes on Wednesday 4th at 20:00
    // and the grower on Thursday 5th at 12:00.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Loaf', 'loaf', 350, variable: false);
    $this->apples = $this->world->catalog->offer(OrderingWorld::GROWER, 'Apples', 'kg', 280, variable: true);
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER]);
    $this->anna = memberActor(ANNA);
    $this->order = fn (?Actor $actor = null, ?string $cycleId = null) => ($this->world->getHouseholdOrder)($actor ?? $this->anna, $cycleId ?? $this->cycleId);
    $this->set = fn (Actor $actor, string $productId, string $quantity) => ($this->world->setOrderLine)($actor, $this->cycleId, $productId, $quantity);
});

it('adds a line while the producer is open (R7, R8, S1)', function () {
    $this->world->clock->travelTo('2026-11-04 19:59');

    ($this->set)($this->anna, $this->bread, '2');

    $lines = ($this->order)()->lines;
    expect($lines)->toHaveCount(1)
        ->and($lines[0]->productId)->toBe($this->bread)
        ->and($lines[0]->quantity)->toBe('2.000');
});

it('rejects a line once the producer is closed and adds nothing (R8, S2)', function () {
    $this->world->clock->travelTo('2026-11-04 20:00');

    expect(fn () => ($this->set)($this->anna, $this->bread, '2'))->toThrow(ProducerClosed::class);

    expect(($this->order)()->lines)->toBe([]);
});

it('keeps editing the producers still open when another has closed (R8, S3)', function () {
    ($this->set)($this->anna, $this->bread, '2');
    ($this->set)($this->anna, $this->apples, '1');
    $this->world->clock->travelTo('2026-11-05 09:00');

    ($this->set)($this->anna, $this->apples, '3');
    expect(fn () => ($this->set)($this->anna, $this->bread, '5'))->toThrow(ProducerClosed::class);
    expect(fn () => ($this->set)($this->anna, $this->bread, '0'))->toThrow(ProducerClosed::class);

    $quantities = collect(($this->order)()->lines)->pluck('quantity', 'productId')->all();
    expect($quantities)->toBe([$this->bread => '2.000', $this->apples => '3.000']);
});

it('shows one order to every member of the household (R7, S4)', function () {
    $biel = memberActor(BIEL);

    ($this->set)($this->anna, $this->apples, '1');
    ($this->set)($biel, $this->bread, '2');

    foreach ([$this->anna, $biel] as $member) {
        $products = collect(($this->order)($member)->lines)->pluck('productId')->all();
        expect($products)->toEqualCanonicalizing([$this->apples, $this->bread]);
    }
});

it('keeps each household\'s order apart (R7)', function () {
    ($this->set)($this->anna, $this->bread, '2');

    expect(($this->order)(memberActor(BIEL, HOUSEHOLD_J))->lines)->toBe([]);
});

it('rejects an account without a household and keeps it from reading an order (R7, S33)', function () {
    $unlinked = new Actor(ANNA, [Role::Member]);

    expect(fn () => ($this->set)($unlinked, $this->bread, '1'))->toThrow(NoHousehold::class);
    expect(fn () => ($this->order)($unlinked))->toThrow(NoHousehold::class);
});

it('rejects an unknown cycle (R7)', function () {
    expect(fn () => ($this->world->setOrderLine)($this->anna, '00000000-0000-7000-8000-0000000000ff', $this->bread, '1'))
        ->toThrow(CycleNotFound::class);
});

it('rejects a producer that does not participate until a manager adds it (R3, R4a, S51)', function () {
    $world = new OrderingWorld('2026-10-22 10:00');
    $bread = $world->catalog->offer(OrderingWorld::BAKER, 'Loaf', 'loaf', 350, variable: false);
    $carrots = $world->catalog->offer(OrderingWorld::GROWER, 'Carrots', 'kg', 200, variable: true);
    $cycleId = ($world->openCycle)($world->manager, '2026-11-07', [OrderingWorld::BAKER]);

    expect(fn () => ($world->setOrderLine)($this->anna, $cycleId, $carrots, '1'))->toThrow(ProducerNotParticipating::class);

    ($world->addParticipant)($world->manager, $cycleId, OrderingWorld::GROWER);
    ($world->setOrderLine)($this->anna, $cycleId, $carrots, '1');
    ($world->setOrderLine)($this->anna, $cycleId, $bread, '1');

    expect(($world->getHouseholdOrder)($this->anna, $cycleId)->lines)->toHaveCount(2);
});

it('rejects a product that is not orderable (R3)', function () {
    $this->world->catalog->deactivate($this->bread);

    expect(fn () => ($this->set)($this->anna, $this->bread, '1'))->toThrow(ProductNotOrderable::class);
    expect(fn () => ($this->set)($this->anna, '00000000-0000-7000-8000-0000000000fe', '1'))->toThrow(ProductNotOrderable::class);
});

it('rejects fractions of a non-variable product, below a thousandth, and accepts a thousandth step (R9, S49)', function () {
    expect(fn () => ($this->set)($this->anna, $this->bread, '0.5'))->toThrow(FractionalQuantityNotAllowed::class);

    ($this->set)($this->anna, $this->apples, '0.250');
    expect(fn () => ($this->set)($this->anna, $this->apples, '0.0005'))->toThrow(InvalidQuantity::class);

    $lines = ($this->order)()->lines;
    expect($lines)->toHaveCount(1)
        ->and($lines[0]->productId)->toBe($this->apples)
        ->and($lines[0]->quantity)->toBe('0.250');
});

it('removes the line when the quantity is set to zero, and ignores zero for a product not in the order (R9, S49)', function () {
    ($this->set)($this->anna, $this->apples, '0.250');
    ($this->set)($this->anna, $this->bread, '0');

    expect(($this->order)()->lines)->toHaveCount(1);

    ($this->set)($this->anna, $this->apples, '0');

    expect(($this->order)()->lines)->toBe([]);
});

it('changes the quantity of an existing line instead of adding another (R7)', function () {
    ($this->set)($this->anna, $this->bread, '2');
    ($this->set)($this->anna, $this->bread, '4');

    $lines = ($this->order)()->lines;
    expect($lines)->toHaveCount(1)
        ->and($lines[0]->quantity)->toBe('4.000');
});

it('snapshots the product when the line is last changed (R10, S5)', function () {
    $beef = $this->world->catalog->offer(OrderingWorld::GROWER, 'Beef', 'kg', 2000, variable: true, containersPerUnit: '2');
    ($this->set)($this->anna, $beef, '1');

    $this->world->catalog->change($beef, name: 'Beef steak', priceCents: 2200);

    $line = ($this->order)()->lines[0];
    expect($line->name)->toBe('Beef')
        ->and($line->unit)->toBe('kg')
        ->and($line->unitPriceCents)->toBe(2000)
        ->and($line->variable)->toBeTrue()
        ->and($line->containersPerUnit)->toBe('2.000');

    ($this->set)($this->anna, $beef, '2');

    $line = ($this->order)()->lines[0];
    expect($line->name)->toBe('Beef steak')
        ->and($line->unitPriceCents)->toBe(2200);
});

it('records who added the line and keeps it when someone else changes the quantity (R10)', function () {
    ($this->set)($this->anna, $this->bread, '1');
    ($this->set)(memberActor(BIEL), $this->bread, '3');

    $line = ($this->order)()->lines[0];
    expect($line->addedBy)->toBe(ANNA)
        ->and($line->quantity)->toBe('3.000');
});

it('allows ordering whatever the wallet balance (R11)', function () {
    ($this->set)($this->anna, $this->apples, '9999.999');

    expect(($this->order)()->lines[0]->quantity)->toBe('9999.999');
});

it('lists the participating producers with whether they are open and what they offer (R3, R8)', function () {
    $this->world->catalog->offer(OrderingWorld::BAKER, 'Roll', 'roll', 120, variable: false);
    $this->world->clock->travelTo('2026-11-05 09:00');

    $producers = collect(($this->order)()->producers)->keyBy('producerId');

    expect($producers[OrderingWorld::BAKER]->open)->toBeFalse()
        ->and($producers[OrderingWorld::GROWER]->open)->toBeTrue()
        ->and(collect($producers[OrderingWorld::BAKER]->products)->pluck('name')->all())->toBe(['Loaf', 'Roll'])
        ->and($producers[OrderingWorld::GROWER]->products[0]->priceCents)->toBe(280);
});
