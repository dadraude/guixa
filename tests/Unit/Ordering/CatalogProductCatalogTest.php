<?php

use Guixa\Ordering\Infrastructure\Catalog\CatalogProductCatalog;
use Tests\Support\Catalog\CatalogWorld;

beforeEach(function () {
    $this->world = new CatalogWorld;
    $this->catalog = new CatalogProductCatalog($this->world->getOrderableProduct, $this->world->listOrderableProducts);
    $this->producerId = ($this->world->createProducer)($this->world->manager, 'Baker', 3, '20:00');
    $this->producer = $this->world->producerActor($this->producerId);
    $this->add = fn (string $name, int $cents = 350, bool $variable = false, ?string $containers = null) => ($this->world->createProduct)(
        $this->producer, $this->producerId, $name, 'loaf', $cents, $variable, $containers,
    );
});

it('hands Ordering the product with the fields a line snapshots (R3, R10)', function () {
    $id = ($this->add)('Loaf', 350, true, '2');

    $product = $this->catalog->orderable($id);

    expect($product->id)->toBe($id)
        ->and($product->producerId)->toBe($this->producerId)
        ->and($product->name)->toBe('Loaf')
        ->and($product->unit)->toBe('loaf')
        ->and($product->price->cents)->toBe(350)
        ->and($product->variable)->toBeTrue()
        ->and($product->containersPerUnit->toString())->toBe('2.000');
});

it('has no containers when the product comes without (R1)', function () {
    expect($this->catalog->orderable(($this->add)('Loaf'))->containersPerUnit)->toBeNull();
});

it('gives nothing for an unknown or deactivated product (R3)', function () {
    $id = ($this->add)('Loaf');
    ($this->world->deactivateProduct)($this->producer, $id);

    expect($this->catalog->orderable($id))->toBeNull()
        ->and($this->catalog->orderable($this->world->products->nextId()))->toBeNull();
});

it('lists a producer\'s orderable products by name and nothing for an unknown producer (R3)', function () {
    ($this->add)('Roll');
    ($this->add)('Loaf');
    ($this->world->deactivateProduct)($this->producer, ($this->add)('Old'));

    expect(array_map(fn ($product) => $product->name, $this->catalog->orderableOf($this->producerId)))->toBe(['Loaf', 'Roll'])
        ->and($this->catalog->orderableOf($this->world->products->nextId()))->toBe([]);
});
