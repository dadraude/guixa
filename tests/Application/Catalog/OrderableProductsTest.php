<?php

use Guixa\Catalog\Domain\Producer;
use Tests\Support\Catalog\CatalogWorld;

beforeEach(function () {
    $this->world = new CatalogWorld;
    $this->baker = ($this->world->createProducer)($this->world->manager, 'Baker', 3, '20:00');
    $this->bakerActor = $this->world->producerActor($this->baker);
});

function deactivateProducerDirectly(CatalogWorld $world, string $producerId): void
{
    $producer = $world->producers->get($producerId);

    $world->producers->add(Producer::restore($producerId, $producer->name(), false, $producer->cutoffRule()));
}

// R3 (catalog part) — only active products of active producers can be ordered

it('returns an active product of an active producer with what a line snapshots (R3, R10)', function () {
    $id = ($this->world->createProduct)($this->bakerActor, $this->baker, 'Bread', 'loaf', 350, false, '2.000');

    $product = ($this->world->getOrderableProduct)($id);

    expect($product->id)->toBe($id)
        ->and($product->producerId)->toBe($this->baker)
        ->and($product->name)->toBe('Bread')
        ->and($product->unit)->toBe('loaf')
        ->and($product->priceCents)->toBe(350)
        ->and($product->variable)->toBeFalse()
        ->and($product->containersPerUnit)->toBe('2.000');
});

it('returns nothing for an unknown or deactivated product (R3)', function () {
    $id = ($this->world->createProduct)($this->bakerActor, $this->baker, 'Bread', 'loaf', 350, false, null);
    ($this->world->deactivateProduct)($this->bakerActor, $id);

    expect(($this->world->getOrderableProduct)($id))->toBeNull()
        ->and(($this->world->getOrderableProduct)('00000000-0000-7000-8000-000000000999'))->toBeNull();

    ($this->world->reactivateProduct)($this->bakerActor, $id);

    expect(($this->world->getOrderableProduct)($id))->not->toBeNull();
});

it('returns nothing for a product of a deactivated producer (R3, R3a)', function () {
    $id = ($this->world->createProduct)($this->bakerActor, $this->baker, 'Bread', 'loaf', 350, false, null);
    deactivateProducerDirectly($this->world, $this->baker);

    expect(($this->world->getOrderableProduct)($id))->toBeNull();
});

it('lists the active products of a producer by name, leaving out inactive ones (R3)', function () {
    ($this->world->createProduct)($this->bakerActor, $this->baker, 'Rolls', 'piece', 100, true, null);
    $bread = ($this->world->createProduct)($this->bakerActor, $this->baker, 'Bread', 'loaf', 350, false, null);
    $old = ($this->world->createProduct)($this->bakerActor, $this->baker, 'Old', 'loaf', 100, false, null);
    ($this->world->deactivateProduct)($this->bakerActor, $old);
    $cheesemaker = ($this->world->createProducer)($this->world->manager, 'Cheesemaker', 2, '12:00');
    ($this->world->createProduct)($this->world->producerActor($cheesemaker), $cheesemaker, 'Cheese', 'kg', 900, true, null);

    $products = ($this->world->listOrderableProducts)($this->baker);

    expect(array_map(fn ($p) => $p->name, $products))->toBe(['Bread', 'Rolls'])
        ->and($products[0]->id)->toBe($bread);
});

it('lists nothing for a deactivated or unknown producer (R3, R3a)', function () {
    ($this->world->createProduct)($this->bakerActor, $this->baker, 'Bread', 'loaf', 350, false, null);
    deactivateProducerDirectly($this->world, $this->baker);

    expect(($this->world->listOrderableProducts)($this->baker))->toBe([])
        ->and(($this->world->listOrderableProducts)('00000000-0000-7000-8000-000000000999'))->toBe([]);
});

// R5 — the producer's default rule, for Ordering

it('returns the producer default cutoff rule and whether it is active (R5)', function () {
    $rule = ($this->world->getProducerCutoffRule)($this->baker);

    expect($rule->active)->toBeTrue()
        ->and($rule->daysBefore)->toBe(3)
        ->and($rule->time)->toBe('20:00');

    deactivateProducerDirectly($this->world, $this->baker);

    expect(($this->world->getProducerCutoffRule)($this->baker)->active)->toBeFalse();
});

it('returns nothing for an unknown producer (R5)', function () {
    expect(($this->world->getProducerCutoffRule)('00000000-0000-7000-8000-000000000999'))->toBeNull();
});
