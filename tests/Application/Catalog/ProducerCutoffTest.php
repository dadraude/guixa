<?php

use Guixa\Catalog\Domain\Error\InvalidCutoffRule;
use Guixa\Catalog\Domain\Error\NameRequired;
use Guixa\Catalog\Domain\Error\ProducerNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\Catalog\CatalogWorld;

beforeEach(function () {
    $this->world = new CatalogWorld;
});

function bakery(CatalogWorld $world): string
{
    return ($world->createProducer)($world->manager, 'Baker', 3, '20:00');
}

// R36 — a manager creates producers

it('lets a manager create a producer with its default cutoff, active (R5, R36)', function () {
    $id = bakery($this->world);

    $producer = ($this->world->getProducer)($id);

    expect($producer->name)->toBe('Baker')
        ->and($producer->active)->toBeTrue()
        ->and($producer->cutoffDaysBefore)->toBe(3)
        ->and($producer->cutoffTime)->toBe('20:00');
});

it('trims the name and rejects a blank one', function () {
    $id = ($this->world->createProducer)($this->world->manager, '  Baker  ', 3, '20:00');

    expect(($this->world->getProducer)($id)->name)->toBe('Baker')
        ->and(fn () => ($this->world->createProducer)($this->world->manager, '   ', 3, '20:00'))->toThrow(NameRequired::class);
});

it('rejects an invalid cutoff when creating a producer (R5)', function () {
    expect(fn () => ($this->world->createProducer)($this->world->manager, 'Baker', 7, '20:00'))->toThrow(InvalidCutoffRule::class)
        ->and($this->world->producers->list())->toBe([]);
});

it('keeps producer creation to managers (R36)', function (Role $role) {
    expect(fn () => ($this->world->createProducer)(new Actor('a', [$role]), 'Baker', 3, '20:00'))
        ->toThrow(NotAllowed::class);
})->with([Role::Member, Role::Producer]);

it('lists the producers by name for a manager only (R36)', function () {
    ($this->world->createProducer)($this->world->manager, 'Cheesemaker', 2, '12:00');
    bakery($this->world);

    $names = array_map(fn ($producer) => $producer->name, ($this->world->listProducers)($this->world->manager));

    expect($names)->toBe(['Baker', 'Cheesemaker'])
        ->and(fn () => ($this->world->listProducers)(new Actor('a', [Role::Member])))->toThrow(NotAllowed::class);
});

it('answers null for an unknown producer', function () {
    expect(($this->world->getProducer)('00000000-0000-7000-8000-0000000000ff'))->toBeNull();
});

// R5 — the default cutoff

it('lets a producer set its own default cutoff (R5)', function () {
    $id = bakery($this->world);

    ($this->world->setDefaultCutoff)($this->world->producerActor($id), $id, 1, '02:30');

    $rule = ($this->world->getDefaultCutoff)($this->world->producerActor($id), $id);
    expect($rule->daysBefore)->toBe(1)
        ->and($rule->time)->toBe('02:30');
});

it('rejects an invalid default cutoff and keeps the old one (R5)', function (int $days, string $time) {
    $id = bakery($this->world);

    expect(fn () => ($this->world->setDefaultCutoff)($this->world->producerActor($id), $id, $days, $time))
        ->toThrow(InvalidCutoffRule::class);

    $rule = ($this->world->getDefaultCutoff)($this->world->producerActor($id), $id);
    expect([$rule->daysBefore, $rule->time])->toBe([3, '20:00']);
})->with([
    'zero days' => [0, '20:00'],
    'seven days' => [7, '20:00'],
    'bad time' => [3, '25:00'],
]);

it('only lets the producer itself change its cutoff (R5)', function () {
    $baker = bakery($this->world);
    $cheesemaker = ($this->world->createProducer)($this->world->manager, 'Cheesemaker', 2, '12:00');

    expect(fn () => ($this->world->setDefaultCutoff)($this->world->producerActor($cheesemaker), $baker, 1, '08:00'))
        ->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->setDefaultCutoff)($this->world->manager, $baker, 1, '08:00'))
        ->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->setDefaultCutoff)(new Actor('a', [Role::Member]), $baker, 1, '08:00'))
        ->toThrow(NotAllowed::class);
});

it('reports an unknown producer when setting its cutoff', function () {
    $unknown = '00000000-0000-7000-8000-0000000000ff';

    expect(fn () => ($this->world->setDefaultCutoff)($this->world->producerActor($unknown), $unknown, 1, '08:00'))
        ->toThrow(ProducerNotFound::class);
});

it('only lets the producer read its own cutoff', function () {
    $baker = bakery($this->world);
    $cheesemaker = ($this->world->createProducer)($this->world->manager, 'Cheesemaker', 2, '12:00');

    expect(fn () => ($this->world->getDefaultCutoff)($this->world->producerActor($cheesemaker), $baker))
        ->toThrow(NotAllowed::class);
});

it('closes orders per the new default after the producer changes it (R5, S38)', function () {
    $id = bakery($this->world);
    ($this->world->setDefaultCutoff)($this->world->producerActor($id), $id, 1, '02:30');

    $producer = $this->world->producers->get($id);

    // Clocks go forward in Madrid on 29 March 2026; pickup is Monday 30th (S38).
    expect($producer->cutoffRule()->closesAt(new DateTimeImmutable('2026-03-30'), $this->world->clock->timezone())->format('Y-m-d H:i'))
        ->toBe('2026-03-29 03:30');
});
