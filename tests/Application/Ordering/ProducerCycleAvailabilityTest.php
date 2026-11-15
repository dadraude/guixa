<?php

use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\Ordering\OrderingWorld;

beforeEach(function () {
    // Thursday 22 October 2026; pickups on Saturday 7 and 14 November, the baker closes on Wednesday 4th at 20:00.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->jam = $this->world->catalog->offer(OrderingWorld::BAKER, 'Jam', 'jar', 450, variable: false);
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 350, variable: false);
    $this->apples = $this->world->catalog->offer(OrderingWorld::GROWER, 'Apples', 'kg', 300, variable: true);
    $this->cycleA = ($this->world->openCycle)($this->world->manager, '2026-11-14', [OrderingWorld::BAKER, OrderingWorld::GROWER]);
    $this->cycleB = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);
    $this->catalogue = new Actor('00000000-0000-7000-8000-0000000000c0', [Role::Catalogue]);
    $this->baker = Actor::producer('00000000-0000-7000-8000-0000000000aa', OrderingWorld::BAKER);
    $this->send = function (string $cycleId) {
        ($this->world->closeProducerNow)($this->world->manager, $cycleId, OrderingWorld::BAKER);
        ($this->world->sendProducerOrder)($this->world->manager, $cycleId, OrderingWorld::BAKER);
    };
});

// D9 — ListProducerUnsentCycles

it('lists the cycles where the producer takes part and its order is not sent, earliest pickup first (D9)', function () {
    ($this->send)($this->cycleB);

    $cycles = ($this->world->listProducerUnsentCycles)($this->catalogue, OrderingWorld::BAKER);

    expect(collect($cycles)->map(fn ($c) => [$c->cycleId, $c->pickupDate])->all())->toBe([[$this->cycleA, '2026-11-14']]);
});

it('leaves out cancelled cycles and cycles where the producer does not take part (D9)', function () {
    ($this->world->cancelCycle)($this->world->manager, $this->cycleB);

    expect(collect(($this->world->listProducerUnsentCycles)($this->catalogue, OrderingWorld::GROWER))->pluck('cycleId')->all())->toBe([$this->cycleA])
        ->and(collect(($this->world->listProducerUnsentCycles)($this->catalogue, OrderingWorld::BAKER))->pluck('cycleId')->all())->toBe([$this->cycleA]);
});

it('lists the unsent cycles for the manager and the producer itself, and refuses other accounts and producers (D9)', function () {
    expect(($this->world->listProducerUnsentCycles)($this->world->manager, OrderingWorld::BAKER))->toHaveCount(2)
        ->and(($this->world->listProducerUnsentCycles)($this->baker, OrderingWorld::BAKER))->toHaveCount(2)
        ->and(fn () => ($this->world->listProducerUnsentCycles)($this->baker, OrderingWorld::GROWER))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->listProducerUnsentCycles)(new Actor('00000000-0000-7000-8000-0000000000c1', [Role::Receiver]), OrderingWorld::BAKER))->toThrow(AuthorizationException::class);
});

// D9, R14 — GetProducerCycleAvailability

it('shows the producer\'s active weekly products with their availability and no totals (D9, R14)', function () {
    ($this->world->setProductAvailability)($this->catalogue, $this->cycleA, $this->jam, false);

    $availability = ($this->world->getProducerCycleAvailability)($this->catalogue, $this->cycleA, OrderingWorld::BAKER);

    expect($availability->canChange)->toBeTrue()
        ->and($availability->pickupDate)->toBe('2026-11-14')
        ->and(collect($availability->products)->map(fn ($p) => [$p->name, $p->unit, $p->available])->all())->toBe([['Bread', 'loaf', true], ['Jam', 'jar', false]])
        ->and(json_encode($availability))->not->toContain('quantity')->not->toContain('Apples');
});

it('cannot change availability once the order is sent, and still shows the products (D9, R3c)', function () {
    ($this->send)($this->cycleB);

    $availability = ($this->world->getProducerCycleAvailability)($this->catalogue, $this->cycleB, OrderingWorld::BAKER);

    expect($availability->canChange)->toBeFalse()
        ->and($availability->products)->toHaveCount(2);
});

it('leaves out deactivated products, which cannot be ordered (R5)', function () {
    $this->world->catalog->deactivate($this->jam);

    expect(collect(($this->world->getProducerCycleAvailability)($this->catalogue, $this->cycleA, OrderingWorld::BAKER)->products)->pluck('name')->all())->toBe(['Bread']);
});

it('answers not found for an unknown or cancelled cycle and one the producer is not in (D9)', function () {
    ($this->world->cancelCycle)($this->world->manager, $this->cycleB);

    expect(fn () => ($this->world->getProducerCycleAvailability)($this->catalogue, '00000000-0000-7000-8000-0000000000fe', OrderingWorld::BAKER))->toThrow(CycleNotFound::class)
        ->and(fn () => ($this->world->getProducerCycleAvailability)($this->catalogue, $this->cycleB, OrderingWorld::BAKER))->toThrow(CycleNotFound::class)
        ->and(fn () => ($this->world->getProducerCycleAvailability)($this->catalogue, $this->cycleB, OrderingWorld::GROWER))->toThrow(CycleNotFound::class);
});

it('refuses accounts that neither own the producer nor administer the catalogue (D9)', function () {
    $grower = Actor::producer('00000000-0000-7000-8000-0000000000ab', OrderingWorld::GROWER);

    expect(fn () => ($this->world->getProducerCycleAvailability)($grower, $this->cycleA, OrderingWorld::BAKER))->toThrow(AuthorizationException::class)
        ->and(($this->world->getProducerCycleAvailability)($this->baker, $this->cycleA, OrderingWorld::BAKER)->products)->toHaveCount(2);
});
