<?php

use Guixa\Ordering\Domain\Error\CutoffNotInFuture;
use Guixa\Ordering\Domain\Error\CutoffOutsideWindow;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\InvalidCutoff;
use Guixa\Ordering\Domain\Error\ProducerNotInCycle;
use Guixa\Shared\Domain\Error\NotAllowed;
use Tests\Support\Ordering\OrderingWorld;

const OTHER_PRODUCER = '00000000-0000-7000-8000-0000000000b3';

beforeEach(function () {
    // Thursday 22 October 2026; the cycle is picked up on Saturday 7 November,
    // so the baker's cutoff is Wednesday 4th at 20:00 and the grower's Thursday 5th at 12:00.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER]);
    $this->participant = fn (string $producerId) => collect(
        collect(($this->world->listCycles)($this->world->manager))->firstWhere('id', $this->cycleId)->participants,
    )->firstWhere('producerId', $producerId);
    $this->baker = fn () => ($this->participant)(OrderingWorld::BAKER);
    $this->grower = fn () => ($this->participant)(OrderingWorld::GROWER);
});

it('moves a cutoff to a later moment and members can order until then (R6, S6)', function () {
    ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, '2026-11-05T12:00');

    expect(($this->baker)()->cutoff)->toBe('2026-11-05T12:00:00+01:00');

    $this->world->clock->travelTo('2026-11-05 11:59');
    expect(($this->baker)()->open)->toBeTrue();

    $this->world->clock->travelTo('2026-11-05 12:00');
    expect(($this->baker)()->open)->toBeFalse();
});

it('is open until its default cutoff when nothing was changed (R6a, S6)', function () {
    $this->world->clock->travelTo('2026-11-04 19:59');
    expect(($this->baker)()->open)->toBeTrue();

    $this->world->clock->travelTo('2026-11-04 20:00');
    expect(($this->baker)()->open)->toBeFalse();
});

it('rejects a cutoff on the pickup date or after it, or 7 or more days before, and keeps the old one (R6, S6)', function (string $at) {
    expect(fn () => ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $at))
        ->toThrow(CutoffOutsideWindow::class);

    expect(($this->baker)()->cutoff)->toBe('2026-11-04T20:00:00+01:00');
})->with([
    'saturday at 08:00' => '2026-11-07T08:00',
    'saturday at midnight' => '2026-11-07T00:00',
    'after the pickup date' => '2026-11-08T10:00',
    'one minute before the window' => '2026-10-31T23:59',
    'seven days before' => '2026-10-31T12:00',
]);

it('accepts a cutoff at the very start of the window and at its last minute (R6)', function (string $at, string $expected) {
    ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $at);

    expect(($this->baker)()->cutoff)->toBe($expected);
})->with([
    'window start' => ['2026-11-01T00:00', '2026-11-01T00:00:00+01:00'],
    'last minute' => ['2026-11-06T23:59', '2026-11-06T23:59:00+01:00'],
]);

it('rejects a cutoff that is not in the future (R6)', function (string $at) {
    $this->world->clock->travelTo('2026-11-04 10:00');

    expect(fn () => ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $at))
        ->toThrow(CutoffNotInFuture::class);
})->with(['now' => '2026-11-04T10:00', 'earlier today' => '2026-11-04T09:00']);

it('reopens a producer whose cutoff has passed by moving the cutoff (R6, S34)', function () {
    $this->world->clock->travelTo('2026-11-05 09:00');
    expect(($this->baker)()->open)->toBeFalse();

    ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, '2026-11-05T12:00');

    expect(($this->baker)()->open)->toBeTrue();
    $this->world->clock->travelTo('2026-11-05 12:00');
    expect(($this->baker)()->open)->toBeFalse();
});

it('reads a cutoff without an offset in the cooperative timezone and honours an explicit offset (R6)', function () {
    ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, '2026-11-05T12:00:00+00:00');

    expect(($this->baker)()->cutoff)->toBe('2026-11-05T13:00:00+01:00');
});

it('rejects a cutoff that is not a date and time (R6)', function (string $at) {
    expect(fn () => ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $at))
        ->toThrow(InvalidCutoff::class);
})->with(['', 'tomorrow', '2026-13-45T10:00', '2026-02-30T10:00', '2026-11-05T12:00:30', '2026-11-05T25:00', 'Thursday']);

it('closes a producer now: its cutoff becomes the current moment (R6, S35)', function () {
    $this->world->clock->travelTo('2026-11-02 15:30');

    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);

    expect(($this->baker)()->cutoff)->toBe('2026-11-02T15:30:00+01:00')
        ->and(($this->baker)()->open)->toBeFalse();
});

it('keeps a producer with no cutoff open until the pickup date at 00:00 (R6, R6a, S35)', function () {
    ($this->world->clearCutoff)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);
    expect(($this->grower)()->cutoff)->toBeNull();

    $this->world->clock->travelTo('2026-11-06 23:59');
    expect(($this->grower)()->open)->toBeTrue();

    $this->world->clock->travelTo('2026-11-07 00:00');
    expect(($this->grower)()->open)->toBeFalse();
});

it('closes a producer with no cutoff from that moment (S35)', function () {
    ($this->world->clearCutoff)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);
    $this->world->clock->travelTo('2026-11-03 18:00');
    expect(($this->grower)()->open)->toBeTrue();

    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);

    expect(($this->grower)()->open)->toBeFalse()
        ->and(($this->grower)()->cutoff)->toBe('2026-11-03T18:00:00+01:00');
});

it('clears a cutoff so the producer stays open past its old one (R6)', function () {
    ($this->world->clearCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);

    $this->world->clock->travelTo('2026-11-06 12:00');
    expect(($this->baker)()->cutoff)->toBeNull()
        ->and(($this->baker)()->open)->toBeTrue();
});

it('lets a producer with no cutoff be given one (R6)', function () {
    ($this->world->clearCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);

    ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, '2026-11-05T12:00');

    expect(($this->baker)()->cutoff)->toBe('2026-11-05T12:00:00+01:00');
});

it('changes only the producer asked for (R6)', function () {
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);

    expect(($this->grower)()->cutoff)->toBe('2026-11-05T12:00:00+01:00');
});

it('rejects a producer that is not in the cycle or an unknown cycle (R6)', function () {
    $manager = $this->world->manager;

    expect(fn () => ($this->world->setCutoff)($manager, $this->cycleId, OTHER_PRODUCER, '2026-11-05T12:00'))
        ->toThrow(ProducerNotInCycle::class)
        ->and(fn () => ($this->world->closeProducerNow)($manager, $this->cycleId, OTHER_PRODUCER))
        ->toThrow(ProducerNotInCycle::class)
        ->and(fn () => ($this->world->clearCutoff)($manager, $this->cycleId, OTHER_PRODUCER))
        ->toThrow(ProducerNotInCycle::class)
        ->and(fn () => ($this->world->setCutoff)($manager, 'unknown', OrderingWorld::BAKER, '2026-11-05T12:00'))
        ->toThrow(CycleNotFound::class);
});

it('keeps the cutoff changes to managers (R6)', function () {
    $member = $this->world->member();

    expect(fn () => ($this->world->setCutoff)($member, $this->cycleId, OrderingWorld::BAKER, '2026-11-05T12:00'))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->closeProducerNow)($member, $this->cycleId, OrderingWorld::BAKER))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->clearCutoff)($member, $this->cycleId, OrderingWorld::BAKER))->toThrow(NotAllowed::class);
});

it('reads a local time repeated by the clocks going back as its first occurrence (R6, S38)', function () {
    $cycleId = ($this->world->openCycle)($this->world->manager, '2026-10-31', [OrderingWorld::BAKER]);

    // Sunday 25 October 2026: 02:30 happens twice in Madrid, +02:00 first and +01:00 after.
    ($this->world->setCutoff)($this->world->manager, $cycleId, OrderingWorld::BAKER, '2026-10-25T02:30');

    $cycle = collect(($this->world->listCycles)($this->world->manager))->firstWhere('id', $cycleId);
    expect($cycle->participants[0]->cutoff)->toBe('2026-10-25T02:30:00+02:00');
});
