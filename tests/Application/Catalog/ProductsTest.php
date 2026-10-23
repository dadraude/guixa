<?php

use Guixa\Catalog\Domain\Error\InvalidContainersPerUnit;
use Guixa\Catalog\Domain\Error\InvalidPrice;
use Guixa\Catalog\Domain\Error\NameRequired;
use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\Error\UnitRequired;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\Catalog\CatalogWorld;

beforeEach(function () {
    $this->world = new CatalogWorld;
    $this->baker = ($this->world->createProducer)($this->world->manager, 'Baker', 3, '20:00');
    $this->bakerActor = $this->world->producerActor($this->baker);
    $this->cheesemaker = ($this->world->createProducer)($this->world->manager, 'Cheesemaker', 2, '12:00');
    $this->cheesemakerActor = $this->world->producerActor($this->cheesemaker);
});

function bread(object $test, string $name = 'Bread'): string
{
    return ($test->world->createProduct)($test->bakerActor, $test->baker, $name, 'loaf', 350, false, '2.000');
}

function bakerProducts(object $test): array
{
    return ($test->world->listProducerProducts)($test->bakerActor, $test->baker);
}

// R1 — a producer creates, edits and deactivates their own products

it('creates a product with its fields, active (R1)', function () {
    $id = bread($this);

    $product = bakerProducts($this)[0];

    expect($product->id)->toBe($id)
        ->and($product->name)->toBe('Bread')
        ->and($product->unit)->toBe('loaf')
        ->and($product->priceCents)->toBe(350)
        ->and($product->variable)->toBeFalse()
        ->and($product->containersPerUnit)->toBe('2.000')
        ->and($product->active)->toBeTrue();
});

it('accepts a price of zero and no containers (R1)', function () {
    ($this->world->createProduct)($this->bakerActor, $this->baker, 'Sample', 'piece', 0, true, null);

    $product = bakerProducts($this)[0];

    expect($product->priceCents)->toBe(0)
        ->and($product->variable)->toBeTrue()
        ->and($product->containersPerUnit)->toBeNull();
});

it('rejects a negative price (R1)', function () {
    expect(fn () => ($this->world->createProduct)($this->bakerActor, $this->baker, 'Bread', 'loaf', -1, false, null))
        ->toThrow(InvalidPrice::class)
        ->and(bakerProducts($this))->toBe([]);
});

it('rejects containers per unit that are zero or not a valid quantity (R1)', function (string $containers) {
    expect(fn () => ($this->world->createProduct)($this->bakerActor, $this->baker, 'Bread', 'loaf', 100, false, $containers))
        ->toThrow(InvalidContainersPerUnit::class);
})->with(['zero' => '0.000', 'four decimals' => '1.2345', 'negative' => '-1', 'text' => 'two']);

it('trims the name and requires a name and a unit (R1)', function () {
    $id = ($this->world->createProduct)($this->bakerActor, $this->baker, '  Bread  ', 'loaf', 100, false, null);

    expect(bakerProducts($this)[0]->name)->toBe('Bread')
        ->and($id)->not->toBe('')
        ->and(fn () => ($this->world->createProduct)($this->bakerActor, $this->baker, '  ', 'loaf', 100, false, null))->toThrow(NameRequired::class)
        ->and(fn () => ($this->world->createProduct)($this->bakerActor, $this->baker, 'Bread', ' ', 100, false, null))->toThrow(UnitRequired::class);
});

it('edits every field of a product (R1)', function () {
    $id = bread($this);

    ($this->world->updateProduct)($this->bakerActor, $id, 'Rye bread', 'kg', 1200, true, null);

    $product = bakerProducts($this)[0];
    expect($product->name)->toBe('Rye bread')
        ->and($product->unit)->toBe('kg')
        ->and($product->priceCents)->toBe(1200)
        ->and($product->variable)->toBeTrue()
        ->and($product->containersPerUnit)->toBeNull();
});

it('validates the same rules when editing and keeps the old values (R1)', function () {
    $id = bread($this);

    expect(fn () => ($this->world->updateProduct)($this->bakerActor, $id, 'Bread', 'loaf', -5, false, null))
        ->toThrow(InvalidPrice::class)
        ->and(bakerProducts($this)[0]->priceCents)->toBe(350);
});

it('deactivates a product, keeps it listed as inactive and reactivates it (R1)', function () {
    $id = bread($this);

    ($this->world->deactivateProduct)($this->bakerActor, $id);
    expect(bakerProducts($this)[0]->active)->toBeFalse();

    ($this->world->reactivateProduct)($this->bakerActor, $id);
    expect(bakerProducts($this)[0]->active)->toBeTrue();
});

it('lists a producer\'s products by name (R2, S57)', function () {
    bread($this, 'Rye');
    bread($this, 'Bread');
    ($this->world->createProduct)($this->cheesemakerActor, $this->cheesemaker, 'Cheese', 'kg', 1500, true, null);

    $names = array_map(fn ($product) => $product->name, bakerProducts($this));

    expect($names)->toBe(['Bread', 'Rye']);
});

it('fails on an unknown product', function () {
    $unknown = '00000000-0000-7000-8000-0000000000ff';

    expect(fn () => ($this->world->updateProduct)($this->bakerActor, $unknown, 'Bread', 'loaf', 100, false, null))->toThrow(ProductNotFound::class)
        ->and(fn () => ($this->world->deactivateProduct)($this->bakerActor, $unknown))->toThrow(ProductNotFound::class)
        ->and(fn () => ($this->world->reactivateProduct)($this->bakerActor, $unknown))->toThrow(ProductNotFound::class);
});

// R2 — a producer cannot see or edit another producer's products

it('keeps another producer from seeing or editing the products (R2, S57)', function () {
    $id = bread($this);

    expect(fn () => ($this->world->listProducerProducts)($this->cheesemakerActor, $this->baker))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->createProduct)($this->cheesemakerActor, $this->baker, 'Fake', 'loaf', 100, false, null))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->updateProduct)($this->cheesemakerActor, $id, 'Stolen', 'loaf', 100, false, null))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->deactivateProduct)($this->cheesemakerActor, $id))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->reactivateProduct)($this->cheesemakerActor, $id))->toThrow(NotAllowed::class);

    $product = bakerProducts($this)[0];
    expect($product->name)->toBe('Bread')->and($product->active)->toBeTrue();
});

it('keeps product management to producers, not managers or members (R1, R2)', function (Actor $actor) {
    $id = bread($this);

    expect(fn () => ($this->world->listProducerProducts)($actor, $this->baker))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->createProduct)($actor, $this->baker, 'Fake', 'loaf', 100, false, null))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->updateProduct)($actor, $id, 'Stolen', 'loaf', 100, false, null))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->deactivateProduct)($actor, $id))->toThrow(NotAllowed::class);
})->with([
    'manager' => fn () => new Actor('00000000-0000-7000-8000-00000000000a', [Role::Manager]),
    'member' => fn () => new Actor('00000000-0000-7000-8000-00000000000b', [Role::Member], 'h1'),
]);
