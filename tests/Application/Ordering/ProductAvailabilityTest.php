<?php

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\LineNotMarked;
use Guixa\Ordering\Domain\Error\NoHousehold;
use Guixa\Ordering\Domain\Error\ProducerClosed;
use Guixa\Ordering\Domain\Error\ProductNotOrderable;
use Guixa\Ordering\Domain\ProducerOrder;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\Ordering\OrderingWorld;

const AVAILABILITY_MEMBER_ANNA = '00000000-0000-7000-8000-0000000000a1';
const MEMBER_BIEL = '00000000-0000-7000-8000-0000000000a2';
const HOUSEHOLD_ONE = '00000000-0000-7000-8000-0000000000e1';

function producerActor(string $producerId): Actor
{
    return Actor::producer('00000000-0000-7000-8000-0000000000aa', $producerId);
}

beforeEach(function () {
    // Thursday 22 October 2026; pickups on Saturday 7 and 14 November, so the baker closes on Wednesday 4th at 20:00.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->jam = $this->world->catalog->offer(OrderingWorld::BAKER, 'Jam', 'jar', 450, variable: false);
    $this->cycleA = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);
    $this->cycleB = ($this->world->openCycle)($this->world->manager, '2026-11-14', [OrderingWorld::BAKER]);
    $this->anna = Actor::memberOf(AVAILABILITY_MEMBER_ANNA, HOUSEHOLD_ONE);
    $this->baker = producerActor(OrderingWorld::BAKER);
    $this->order = fn (string $cycleId) => ($this->world->getHouseholdOrder)($this->anna, $cycleId);
    $this->marked = fn (string $cycleId) => collect(($this->order)($cycleId)->lines)->pluck('notDelivered', 'productId')->all();
    $this->available = fn (string $cycleId, bool $available) => ($this->world->setProductAvailability)($this->baker, $cycleId, $this->jam, $available);
    $this->orderJam = function (string $cycleId) {
        ($this->world->setOrderLine)($this->anna, $cycleId, $this->jam, '2');
    };
});

// R3c, S48 — unavailable in one cycle

it('marks the line in the cycle where the product is unavailable and leaves the other cycle alone (R3b, R3c, S48)', function () {
    ($this->orderJam)($this->cycleA);
    ($this->orderJam)($this->cycleB);

    ($this->available)($this->cycleA, false);

    expect(($this->marked)($this->cycleA))->toBe([$this->jam => true])
        ->and(($this->marked)($this->cycleB))->toBe([$this->jam => false]);
});

it('lifts the mark when the product becomes available again before the order is sent (R3b, S48)', function () {
    ($this->orderJam)($this->cycleA);
    ($this->available)($this->cycleA, false);

    ($this->available)($this->cycleA, true);

    expect(($this->marked)($this->cycleA))->toBe([$this->jam => false]);
});

it('does not let a member newly order a product while it is unavailable, and stops offering it (R3, R3c)', function () {
    ($this->available)($this->cycleA, false);

    expect(fn () => ($this->world->setOrderLine)($this->anna, $this->cycleA, $this->jam, '1'))->toThrow(ProductNotOrderable::class);
    expect(($this->order)($this->cycleA)->lines)->toBe([]);
    expect(($this->order)($this->cycleA)->producers[0]->products)->toBe([]);

    ($this->world->setOrderLine)($this->anna, $this->cycleB, $this->jam, '1');
    expect(($this->order)($this->cycleB)->producers[0]->products)->toHaveCount(1);

    ($this->available)($this->cycleA, true);
    ($this->orderJam)($this->cycleA);
    expect(($this->order)($this->cycleA)->lines)->toHaveCount(1);
});

it('ignores marking a product unavailable or available twice (R3c)', function () {
    ($this->orderJam)($this->cycleA);

    ($this->available)($this->cycleA, false);
    ($this->available)($this->cycleA, false);
    expect(($this->marked)($this->cycleA))->toBe([$this->jam => true]);

    ($this->available)($this->cycleA, true);
    ($this->available)($this->cycleA, true);
    expect(($this->marked)($this->cycleA))->toBe([$this->jam => false]);
});

it('lets only the producer of the product mark it (R3c, R2)', function () {
    $other = producerActor(OrderingWorld::GROWER);

    expect(fn () => ($this->world->setProductAvailability)($other, $this->cycleA, $this->jam, false))->toThrow(NotAllowed::class);
    expect(fn () => ($this->world->setProductAvailability)($this->anna, $this->cycleA, $this->jam, false))->toThrow(NotAllowed::class);
    expect(($this->world->getHouseholdOrder)($this->anna, $this->cycleA)->producers[0]->products)->toHaveCount(1);
});

it('rejects an unknown cycle and an unknown or deactivated product (R3c)', function () {
    $unknown = '00000000-0000-7000-8000-0000000000fe';

    expect(fn () => ($this->world->setProductAvailability)($this->baker, $unknown, $this->jam, false))->toThrow(CycleNotFound::class);

    $this->world->catalog->deactivate($this->jam);
    expect(fn () => ($this->world->setProductAvailability)($this->baker, $this->cycleA, $this->jam, false))->toThrow(ProductNotOrderable::class);
    expect(fn () => ($this->world->setProductAvailability)($this->baker, $this->cycleA, $unknown, false))->toThrow(ProductNotOrderable::class);
});

// R3b, S32 — deactivated product

it('marks the line of a deactivated product, which the member can remove after the cutoff (R3b, R8, S32)', function () {
    ($this->orderJam)($this->cycleA);
    $this->world->catalog->deactivate($this->jam);
    $this->world->clock->travelTo('2026-11-04 20:00');

    expect(($this->marked)($this->cycleA))->toBe([$this->jam => true]);
    expect(fn () => ($this->world->setOrderLine)($this->anna, $this->cycleA, $this->jam, '0'))->toThrow(ProducerClosed::class);

    ($this->world->removeMarkedLine)($this->anna, $this->cycleA, $this->jam);

    expect(($this->order)($this->cycleA)->lines)->toBe([]);
});

it('keeps a marked line in the order until the member removes it (R3b, S32)', function () {
    ($this->orderJam)($this->cycleA);
    $this->world->catalog->deactivate($this->jam);

    expect(($this->order)($this->cycleA)->lines)->toHaveCount(1);
});

it('lifts the mark when the product is orderable again, and a removed line does not come back (R3b)', function () {
    ($this->orderJam)($this->cycleA);
    ($this->orderJam)($this->cycleB);
    $this->world->catalog->deactivate($this->jam);
    ($this->world->removeMarkedLine)($this->anna, $this->cycleB, $this->jam);

    $this->world->catalog->reactivate($this->jam);

    expect(($this->marked)($this->cycleA))->toBe([$this->jam => false])
        ->and(($this->order)($this->cycleB)->lines)->toBe([]);
});

it('marks the lines of a producer that does not participate any more, and the member can remove them (R6c, R8)', function () {
    ($this->orderJam)($this->cycleA);
    $cycle = $this->world->cycles->get($this->cycleA);
    $this->world->cycles->add(Cycle::restore($cycle->id, $cycle->pickupDate(), [ProducerOrder::restore(OrderingWorld::BAKER, false, null)]));

    expect(($this->marked)($this->cycleA))->toBe([$this->jam => true]);

    ($this->world->removeMarkedLine)($this->anna, $this->cycleA, $this->jam);

    expect(($this->order)($this->cycleA)->lines)->toBe([]);
});

it('refuses to remove a line that is not marked, and ignores a product that is not in the order (R8)', function () {
    ($this->orderJam)($this->cycleA);
    $this->world->clock->travelTo('2026-11-04 20:00');

    expect(fn () => ($this->world->removeMarkedLine)($this->anna, $this->cycleA, $this->jam))->toThrow(LineNotMarked::class);
    expect(($this->order)($this->cycleA)->lines)->toHaveCount(1);

    ($this->world->removeMarkedLine)($this->anna, $this->cycleB, $this->jam);
    expect(($this->order)($this->cycleB)->lines)->toBe([]);
});

it('lets any member of the household remove a marked line and refuses an account without a household (R7, S33)', function () {
    ($this->orderJam)($this->cycleA);
    ($this->available)($this->cycleA, false);

    expect(fn () => ($this->world->removeMarkedLine)(new Actor(MEMBER_BIEL, [Role::Member]), $this->cycleA, $this->jam))->toThrow(NoHousehold::class);

    ($this->world->removeMarkedLine)(Actor::memberOf(MEMBER_BIEL, HOUSEHOLD_ONE), $this->cycleA, $this->jam);

    expect(($this->order)($this->cycleA)->lines)->toBe([]);
});

it('rejects removing a marked line in an unknown cycle (R3b)', function () {
    expect(fn () => ($this->world->removeMarkedLine)($this->anna, '00000000-0000-7000-8000-0000000000fe', $this->jam))->toThrow(CycleNotFound::class);
});

// R1 (lock) — the product has unsettled lines

it('answers whether a product has lines in a cycle that is not settled (R1, S5)', function () {
    expect(($this->world->hasUnsettledLines)($this->jam))->toBeFalse();

    ($this->orderJam)($this->cycleA);

    expect(($this->world->hasUnsettledLines)($this->jam))->toBeTrue()
        ->and(($this->world->hasUnsettledLines)('00000000-0000-7000-8000-0000000000fe'))->toBeFalse();

    ($this->world->setOrderLine)($this->anna, $this->cycleA, $this->jam, '0');

    expect(($this->world->hasUnsettledLines)($this->jam))->toBeFalse();
});
