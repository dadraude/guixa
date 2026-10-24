<?php

use Guixa\Catalog\Domain\Error\ProducerNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\Catalog\CatalogWorld;

beforeEach(function () {
    $this->world = new CatalogWorld;
    $this->baker = ($this->world->createProducer)($this->world->manager, 'Baker', 3, '20:00');
    $this->isActive = fn () => collect(($this->world->listProducers)($this->world->manager))->firstWhere('id', $this->baker)->active;
});

// R3a, S50 — deactivating and reactivating a producer

it('deactivates a producer: it loses the role and stops participating where its order is not sent (R3a, S50)', function () {
    ($this->world->deactivateProducer)($this->world->manager, $this->baker);

    expect(($this->isActive)())->toBeFalse()
        ->and($this->world->access->isSuspended($this->baker))->toBeTrue()
        ->and($this->world->cycles->stopped)->toBe([$this->baker]);
});

it('stops offering the products of a deactivated producer (R3a)', function () {
    $bread = ($this->world->createProduct)($this->world->producerActor($this->baker), $this->baker, 'Bread', 'loaf', 350, false, null);

    ($this->world->deactivateProducer)($this->world->manager, $this->baker);

    expect(($this->world->getOrderableProduct)($bread))->toBeNull()
        ->and(($this->world->getProducerCutoffRule)($this->baker)->active)->toBeFalse();
});

it('reactivates a producer and restores the role, not the participation (R3a, S50)', function () {
    ($this->world->deactivateProducer)($this->world->manager, $this->baker);

    ($this->world->reactivateProducer)($this->world->manager, $this->baker);

    expect(($this->isActive)())->toBeTrue()
        ->and($this->world->access->isSuspended($this->baker))->toBeFalse()
        ->and($this->world->cycles->stopped)->toBe([$this->baker]);
});

it('can be repeated without harm (R3a)', function () {
    ($this->world->deactivateProducer)($this->world->manager, $this->baker);
    ($this->world->deactivateProducer)($this->world->manager, $this->baker);
    expect(($this->isActive)())->toBeFalse();

    ($this->world->reactivateProducer)($this->world->manager, $this->baker);
    ($this->world->reactivateProducer)($this->world->manager, $this->baker);
    expect(($this->isActive)())->toBeTrue();
});

it('rejects an unknown producer and changes nothing (R3a)', function () {
    $unknown = '00000000-0000-7000-8000-0000000000fe';

    expect(fn () => ($this->world->deactivateProducer)($this->world->manager, $unknown))->toThrow(ProducerNotFound::class)
        ->and(fn () => ($this->world->reactivateProducer)($this->world->manager, $unknown))->toThrow(ProducerNotFound::class)
        ->and($this->world->cycles->stopped)->toBe([]);
});

it('keeps deactivating and reactivating to managers (R3a)', function (Actor $actor) {
    expect(fn () => ($this->world->deactivateProducer)($actor, $this->baker))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->reactivateProducer)($actor, $this->baker))->toThrow(NotAllowed::class)
        ->and(($this->isActive)())->toBeTrue()
        ->and($this->world->cycles->stopped)->toBe([]);
})->with([
    'the producer itself' => fn () => new Actor('a', [Role::Producer], null, '00000000-0000-7000-8000-0000000000b1'),
    'a member' => fn () => new Actor('a', [Role::Member], '00000000-0000-7000-8000-0000000000dd'),
]);
