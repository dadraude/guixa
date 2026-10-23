<?php

use Guixa\Ordering\Domain\Error\CycleAlreadyExists;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\InvalidPickupDate;
use Guixa\Ordering\Domain\Error\InvalidPickupWeekday;
use Guixa\Ordering\Domain\Error\NotOnPickupWeekday;
use Guixa\Ordering\Domain\Error\PickupDateNotInFuture;
use Guixa\Ordering\Domain\Error\PickupWeekdayAlreadySet;
use Guixa\Ordering\Domain\Error\PickupWeekdayNotSet;
use Guixa\Ordering\Domain\Error\ProducerNotActive;
use Guixa\Shared\Domain\Error\NotAllowed;
use Tests\Support\Ordering\OrderingWorld;

beforeEach(function () {
    // Thursday 22 October 2026; the next Saturdays are 24 and 31 October and 7 and 14 November.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->participantsOf = fn (string $cycleId) => collect(($this->world->listCycles)($this->world->manager))
        ->firstWhere('id', $cycleId)->participants;
    $this->cutoffOf = fn (string $cycleId, string $producerId) => collect(($this->participantsOf)($cycleId))
        ->firstWhere('producerId', $producerId)->cutoff;
});

it('has no pickup weekday until one is set (R4c)', function () {
    $world = new OrderingWorld(pickupWeekday: null);

    expect(($world->getPickupWeekday)($world->manager))->toBeNull();
});

it('lets a manager change the pickup weekday (R4c)', function () {
    ($this->world->setPickupWeekday)($this->world->manager, 5);

    expect(($this->world->getPickupWeekday)($this->world->manager))->toBe(5);
});

it('rejects a pickup weekday that is not a day of the week (R4c)', function (int $weekday) {
    expect(fn () => ($this->world->setPickupWeekday)($this->world->manager, $weekday))
        ->toThrow(InvalidPickupWeekday::class);
    expect(($this->world->getPickupWeekday)($this->world->manager))->toBe(6);
})->with([0, 8, -1]);

it('keeps the pickup weekday and the cycles to managers (R4c)', function () {
    $member = $this->world->member();

    expect(fn () => ($this->world->setPickupWeekday)($member, 5))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->getPickupWeekday)($member))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->openCycle)($member, '2026-11-07', []))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->addParticipant)($member, 'any', OrderingWorld::BAKER))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->listCycles)($member))->toThrow(NotAllowed::class);
});

it('opens a cycle on the pickup weekday with the baker participating and computes its cutoff (R4, R4a, R5, S6)', function () {
    $id = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);

    $cycles = ($this->world->listCycles)($this->world->manager);
    expect($cycles)->toHaveCount(1)
        ->and($cycles[0]->id)->toBe($id)
        ->and($cycles[0]->pickupDate)->toBe('2026-11-07')
        ->and($cycles[0]->participants)->toHaveCount(1)
        ->and($cycles[0]->participants[0]->producerId)->toBe(OrderingWorld::BAKER)
        ->and($cycles[0]->participants[0]->participating)->toBeTrue()
        // Wednesday 4 November at 20:00, Madrid.
        ->and($cycles[0]->participants[0]->cutoff)->toBe('2026-11-04T20:00:00+01:00');
});

it('rejects a pickup date that is not on the pickup weekday (S37, R4c)', function () {
    // Friday 13 November.
    expect(fn () => ($this->world->openCycle)($this->world->manager, '2026-11-13', [OrderingWorld::BAKER]))
        ->toThrow(NotOnPickupWeekday::class);

    expect(($this->world->listCycles)($this->world->manager))->toBeEmpty();
});

it('rejects a pickup date that is today or in the past (R4)', function (string $weekdayDate, int $weekday) {
    ($this->world->setPickupWeekday)($this->world->manager, $weekday);

    expect(fn () => ($this->world->openCycle)($this->world->manager, $weekdayDate, []))
        ->toThrow(PickupDateNotInFuture::class);
})->with([
    'a past Saturday' => ['2026-10-17', 6],
    'today, a Thursday' => ['2026-10-22', 4],
]);

it('opens a cycle for tomorrow when the weekday matches (R4)', function () {
    ($this->world->setPickupWeekday)($this->world->manager, 5);

    $id = ($this->world->openCycle)($this->world->manager, '2026-10-23', []);

    expect(($this->world->cycles)->get($id))->not->toBeNull();
});

it('rejects a malformed pickup date (R4)', function (string $date) {
    expect(fn () => ($this->world->openCycle)($this->world->manager, $date, []))
        ->toThrow(InvalidPickupDate::class);
})->with(['07/11/2026', '2026-02-30', 'tomorrow', '']);

it('allows only one cycle per pickup date (R4)', function () {
    ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);

    expect(fn () => ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::GROWER]))
        ->toThrow(CycleAlreadyExists::class);

    expect(($this->world->listCycles)($this->world->manager))->toHaveCount(1);
});

it('lets several cycles be open at once, ordered by pickup date (R4)', function () {
    ($this->world->openCycle)($this->world->manager, '2026-11-14', []);
    ($this->world->openCycle)($this->world->manager, '2026-11-07', []);
    ($this->world->openCycle)($this->world->manager, '2026-12-05', []);

    $dates = array_map(fn ($cycle) => $cycle->pickupDate, ($this->world->listCycles)($this->world->manager));

    expect($dates)->toBe(['2026-11-07', '2026-11-14', '2026-12-05']);
});

it('opens a cycle with no participating producer (R4a, R12a)', function () {
    $id = ($this->world->openCycle)($this->world->manager, '2026-11-07', []);

    expect(($this->participantsOf)($id))->toBeEmpty();
});

it('rejects a producer that is unknown or inactive, and opens nothing (R4a, S50)', function (string $producerId) {
    $this->world->rules->deactivate(OrderingWorld::GROWER);

    expect(fn () => ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, $producerId]))
        ->toThrow(ProducerNotActive::class);

    expect(($this->world->listCycles)($this->world->manager))->toBeEmpty();
})->with([
    'inactive' => OrderingWorld::GROWER,
    'unknown' => '00000000-0000-7000-8000-0000000000ff',
]);

it('gives a participant no cutoff when the moment has already passed (R5)', function () {
    // Wednesday 4 November 2026 at 21:00, after the baker's 20:00 cutoff for Saturday 7th.
    $world = new OrderingWorld('2026-11-04 21:00');

    $id = ($world->openCycle)($world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER]);

    $cycle = collect(($world->listCycles)($world->manager))->firstWhere('id', $id);
    $cutoffs = collect($cycle->participants)->pluck('cutoff', 'producerId')->all();
    // The grower's 2 days before at 12:00 is Thursday 5th, still ahead.
    expect($cutoffs)->toBe([OrderingWorld::BAKER => null, OrderingWorld::GROWER => '2026-11-05T12:00:00+01:00']);
});

it('computes the cutoff on the day clocks go forward (S38)', function () {
    // Clocks in Madrid go forward on Sunday 28 March 2027 at 02:00; the pickup is that Monday.
    ($this->world->setPickupWeekday)($this->world->manager, 1);
    $this->world->rules->activate(OrderingWorld::BAKER, 1, '02:30');

    $id = ($this->world->openCycle)($this->world->manager, '2027-03-29', [OrderingWorld::BAKER]);

    expect(($this->cutoffOf)($id, OrderingWorld::BAKER))->toBe('2027-03-28T03:30:00+02:00');
});

it('keeps the pickup date of a cycle opened before the weekday changes (R4c)', function () {
    $id = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);

    ($this->world->setPickupWeekday)($this->world->manager, 5);

    $cycle = collect(($this->world->listCycles)($this->world->manager))->firstWhere('id', $id);
    expect($cycle->pickupDate)->toBe('2026-11-07');
    // New cycles follow the new weekday: Friday 13th now works and Saturday 14th does not.
    expect(($this->world->openCycle)($this->world->manager, '2026-11-13', []))->toBeString();
    expect(fn () => ($this->world->openCycle)($this->world->manager, '2026-11-14', []))
        ->toThrow(NotOnPickupWeekday::class);
});

it('makes more producers participate after the cycle is opened (R4a, S51)', function () {
    $id = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);

    ($this->world->addParticipant)($this->world->manager, $id, OrderingWorld::GROWER);

    $participants = collect(($this->participantsOf)($id))->pluck('participating', 'producerId')->all();
    expect($participants)->toBe([OrderingWorld::BAKER => true, OrderingWorld::GROWER => true])
        // Thursday 5 November at 12:00, 2 days before pickup.
        ->and(($this->cutoffOf)($id, OrderingWorld::GROWER))->toBe('2026-11-05T12:00:00+01:00');
});

it('computes the cutoff of a producer added later from its default at that moment (R5)', function () {
    $id = ($this->world->openCycle)($this->world->manager, '2026-11-07', []);
    $this->world->rules->activate(OrderingWorld::BAKER, 1, '08:00');

    ($this->world->addParticipant)($this->world->manager, $id, OrderingWorld::BAKER);

    expect(($this->cutoffOf)($id, OrderingWorld::BAKER))->toBe('2026-11-06T08:00:00+01:00');
});

it('gives a producer added after its cutoff passed no cutoff (R5)', function () {
    $id = ($this->world->openCycle)($this->world->manager, '2026-11-07', []);
    $this->world->clock->travelTo('2026-11-04 21:00');

    ($this->world->addParticipant)($this->world->manager, $id, OrderingWorld::BAKER);

    expect(($this->cutoffOf)($id, OrderingWorld::BAKER))->toBeNull();
});

it('never recomputes the cutoff of a producer already participating (R5)', function () {
    $id = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);
    $this->world->rules->activate(OrderingWorld::BAKER, 1, '08:00');

    ($this->world->addParticipant)($this->world->manager, $id, OrderingWorld::BAKER);

    expect(($this->cutoffOf)($id, OrderingWorld::BAKER))->toBe('2026-11-04T20:00:00+01:00')
        ->and(($this->participantsOf)($id))->toHaveCount(1);
});

it('rejects adding an inactive producer or to an unknown cycle (R4a, S50)', function () {
    $id = ($this->world->openCycle)($this->world->manager, '2026-11-07', []);
    $this->world->rules->deactivate(OrderingWorld::GROWER);

    expect(fn () => ($this->world->addParticipant)($this->world->manager, $id, OrderingWorld::GROWER))
        ->toThrow(ProducerNotActive::class)
        ->and(fn () => ($this->world->addParticipant)($this->world->manager, $this->world->cycles->nextId(), OrderingWorld::BAKER))
        ->toThrow(CycleNotFound::class);

    expect(($this->participantsOf)($id))->toBeEmpty();
});

// R4c — no default weekday: opening a cycle asks for it, permanent or provisional

it('cannot open a cycle while the cooperative has no pickup weekday and none is given (R4c)', function () {
    $world = new OrderingWorld(pickupWeekday: null);

    expect(fn () => ($world->openCycle)($world->manager, '2026-11-07', [OrderingWorld::BAKER]))
        ->toThrow(PickupWeekdayNotSet::class);

    expect(($world->listCycles)($world->manager))->toBeEmpty();
});

it('opens a cycle with a provisional weekday and saves nothing (R4c)', function () {
    $world = new OrderingWorld(pickupWeekday: null);

    $id = ($world->openCycle)($world->manager, '2026-11-13', [], 5, false);

    expect($world->cycles->get($id))->not->toBeNull()
        ->and(($world->getPickupWeekday)($world->manager))->toBeNull()
        ->and(fn () => ($world->openCycle)($world->manager, '2026-11-20', []))->toThrow(PickupWeekdayNotSet::class);
});

it('opens a cycle with a permanent weekday and saves it for the next ones (R4c)', function () {
    $world = new OrderingWorld(pickupWeekday: null);

    ($world->openCycle)($world->manager, '2026-11-13', [], 5, true);

    expect(($world->getPickupWeekday)($world->manager))->toBe(5)
        ->and(($world->openCycle)($world->manager, '2026-11-20', []))->toBeString();
});

it('checks the pickup date against the weekday given, permanent or not (R4c)', function (bool $permanent) {
    $world = new OrderingWorld(pickupWeekday: null);

    // 14 November is a Saturday, and the weekday given is Friday.
    expect(fn () => ($world->openCycle)($world->manager, '2026-11-14', [], 5, $permanent))
        ->toThrow(NotOnPickupWeekday::class);

    expect(($world->listCycles)($world->manager))->toBeEmpty()
        ->and(($world->getPickupWeekday)($world->manager))->toBeNull();
})->with([true, false]);

it('rejects a weekday given that is not a day of the week and saves nothing (R4c)', function () {
    $world = new OrderingWorld(pickupWeekday: null);

    expect(fn () => ($world->openCycle)($world->manager, '2026-11-13', [], 8, true))
        ->toThrow(InvalidPickupWeekday::class);

    expect(($world->getPickupWeekday)($world->manager))->toBeNull();
});

it('refuses a weekday given when the cooperative already has one (R4c)', function (bool $permanent) {
    expect(fn () => ($this->world->openCycle)($this->world->manager, '2026-11-13', [], 5, $permanent))
        ->toThrow(PickupWeekdayAlreadySet::class);

    expect(($this->world->listCycles)($this->world->manager))->toBeEmpty()
        ->and(($this->world->getPickupWeekday)($this->world->manager))->toBe(6);
})->with([true, false]);
