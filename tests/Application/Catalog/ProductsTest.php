<?php

use Guixa\Catalog\Application\CreateStockProduct;
use Guixa\Catalog\Domain\Error\InvalidContainersPerUnit;
use Guixa\Catalog\Domain\Error\InvalidPrice;
use Guixa\Catalog\Domain\Error\NameRequired;
use Guixa\Catalog\Domain\Error\ProductHasUnsettledLines;
use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\Error\UnitRequired;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
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

// R1 (lock), S5 — unit and variable flag while the product has lines in a cycle that is not settled

it('keeps the unit and the variable flag while the product has unsettled lines, and changes the rest (R1, S5)', function () {
    $id = bread($this);
    $this->world->cycles->lock($id);

    ($this->world->updateProduct)($this->bakerActor, $id, 'Sourdough', 'loaf', 400, false, '3.000');

    $product = bakerProducts($this)[0];
    expect($product->name)->toBe('Sourdough')
        ->and($product->priceCents)->toBe(400)
        ->and($product->containersPerUnit)->toBe('3.000');

    expect(fn () => ($this->world->updateProduct)($this->bakerActor, $id, 'Sourdough', 'kg', 400, false, '3.000'))->toThrow(ProductHasUnsettledLines::class);
    expect(fn () => ($this->world->updateProduct)($this->bakerActor, $id, 'Sourdough', 'loaf', 400, true, '3.000'))->toThrow(ProductHasUnsettledLines::class);

    $product = bakerProducts($this)[0];
    expect($product->unit)->toBe('loaf')->and($product->variable)->toBeFalse();
});

it('changes the unit and the variable flag once the product has no unsettled lines (R1)', function () {
    $id = bread($this);
    $this->world->cycles->lock($id);
    $this->world->cycles->release($id);

    ($this->world->updateProduct)($this->bakerActor, $id, 'Bread', 'kg', 350, true, null);

    $product = bakerProducts($this)[0];
    expect($product->unit)->toBe('kg')->and($product->variable)->toBeTrue();
});

// R2 — a producer cannot see or edit another producer's products

it('keeps another producer from seeing or editing the products (R2, S57)', function () {
    $id = bread($this);

    expect(fn () => ($this->world->listProducerProducts)($this->cheesemakerActor, $this->baker))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->createProduct)($this->cheesemakerActor, $this->baker, 'Fake', 'loaf', 100, false, null))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->updateProduct)($this->cheesemakerActor, $id, 'Stolen', 'loaf', 100, false, null))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->deactivateProduct)($this->cheesemakerActor, $id))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->reactivateProduct)($this->cheesemakerActor, $id))->toThrow(AuthorizationException::class);

    $product = bakerProducts($this)[0];
    expect($product->name)->toBe('Bread')->and($product->active)->toBeTrue();
});

it('keeps product management to producers and the catalogue roles, not members, finance or receivers (R1, R2, R14)', function (Actor $actor) {
    $id = bread($this);

    expect(fn () => ($this->world->listProducerProducts)($actor, $this->baker))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->createProduct)($actor, $this->baker, 'Fake', 'loaf', 100, false, null))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->updateProduct)($actor, $id, 'Stolen', 'loaf', 100, false, null))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->deactivateProduct)($actor, $id))->toThrow(AuthorizationException::class);
})->with([
    'finance' => fn () => new Actor('00000000-0000-7000-8000-00000000000c', [Role::Finance]),
    'receiver' => fn () => new Actor('00000000-0000-7000-8000-00000000000d', [Role::Receiver]),
    'member' => fn () => Actor::memberOf('00000000-0000-7000-8000-00000000000b', 'h1'),
]);

// R3 — Catalogue and the manager administer any producer's weekly products under the producer's rules

it('lets a catalogue actor edit another producer\'s weekly product, which a producer cannot do (R3, S4)', function () {
    $id = bread($this);
    $catalogue = $this->world->catalogueActor();

    ($this->world->updateProduct)($catalogue, $id, 'Rye bread', 'loaf', 400, false, null);

    expect(bakerProducts($this)[0]->name)->toBe('Rye bread')
        ->and(bakerProducts($this)[0]->priceCents)->toBe(400);
});

it('lets a manager edit another producer\'s weekly product (R3, S4)', function () {
    $id = bread($this);

    ($this->world->updateProduct)($this->world->manager, $id, 'Rye bread', 'loaf', 400, false, null);

    expect(bakerProducts($this)[0]->priceCents)->toBe(400);
});

it('saves the price and rejects the unit of a catalogue edit while lines are unsettled, then the producer\'s later edit replaces it (R3, S4)', function (Actor $actor) {
    $id = bread($this);
    $this->world->cycles->lock($id);

    ($this->world->updateProduct)($actor, $id, 'Bread', 'loaf', 500, false, '2.000');
    expect(fn () => ($this->world->updateProduct)($actor, $id, 'Bread', 'kg', 500, false, '2.000'))->toThrow(ProductHasUnsettledLines::class);
    expect(bakerProducts($this)[0]->priceCents)->toBe(500)->and(bakerProducts($this)[0]->unit)->toBe('loaf');

    ($this->world->updateProduct)($this->bakerActor, $id, 'Bread', 'loaf', 360, false, '2.000');
    expect(bakerProducts($this)[0]->priceCents)->toBe(360);
})->with([
    'catalogue' => fn () => (new CatalogWorld)->catalogueActor(),
    'manager' => fn () => Actor::manager('00000000-0000-7000-8000-00000000000a'),
]);

it('lets a catalogue actor or manager create, deactivate, reactivate and delete unused products of any producer (R3)', function (Actor $actor) {
    $id = ($this->world->createProduct)($actor, $this->baker, 'Focaccia', 'piece', 250, false, null);
    expect(bakerProducts($this)[0]->id)->toBe($id);

    ($this->world->deactivateProduct)($actor, $id);
    expect(bakerProducts($this)[0]->active)->toBeFalse();
    ($this->world->reactivateProduct)($actor, $id);
    expect(bakerProducts($this)[0]->active)->toBeTrue();

    ($this->world->deleteUnusedProduct)($actor, $id);
    expect(bakerProducts($this))->toBe([]);
})->with([
    'catalogue' => fn () => (new CatalogWorld)->catalogueActor(),
    'manager' => fn () => Actor::manager('00000000-0000-7000-8000-00000000000a'),
]);

it('lets a producer\'s account with Catalogue edit other producers\' products through the role only (R3, S5)', function () {
    $id = bread($this);
    $both = new Actor('00000000-0000-7000-8000-0000000000dd', [Role::Producer, Role::Catalogue], producerId: $this->cheesemaker);

    ($this->world->updateProduct)($both, $id, 'Bread', 'loaf', 420, false, null);

    expect(bakerProducts($this)[0]->priceCents)->toBe(420)
        ->and(fn () => ($this->world->listProducerProducts)($both, $this->baker))->toThrow(AuthorizationException::class)
        ->and(($this->world->listProducerProducts)($both, $this->cheesemaker))->toBe([]);
});

it('keeps stock products out of the weekly policy for catalogue edits (R3)', function () {
    $catalogue = $this->world->catalogueActor();
    $stock = (new CreateStockProduct($this->world->products, $this->world->producers))($this->world->manager, $this->baker, 'Honey', 'jar', 650, false, null);

    expect(fn () => ($this->world->updateProduct)($catalogue, $stock, 'Honey', 'jar', 100, false, null))->toThrow(AuthorizationException::class);
});

// R5 — the producer list and a producer's products are read through gates

it('lists producers for the manager and Catalogue, deactivated ones marked, and refuses other roles (R5, R14)', function () {
    ($this->world->deactivateProducer)($this->world->manager, $this->cheesemaker);

    foreach ([$this->world->manager, $this->world->catalogueActor()] as $actor) {
        $listed = collect(($this->world->listProducers)($actor))->keyBy('id');
        expect($listed[$this->baker]->active)->toBeTrue()->and($listed[$this->cheesemaker]->active)->toBeFalse();
    }

    foreach ([new Actor('f', [Role::Finance]), new Actor('r', [Role::Receiver]), Actor::memberOf('m', 'h1')] as $actor) {
        expect(fn () => ($this->world->listProducers)($actor))->toThrow(AuthorizationException::class);
    }
});

it('lists the products of a producer with can flags to the manager and Catalogue only (R3, R5, R14)', function () {
    $id = bread($this);

    foreach ([$this->world->manager, $this->world->catalogueActor()] as $actor) {
        $row = ($this->world->listProductsOfProducer)($actor, $this->baker)[0];
        expect($row->id)->toBe($id)->and($row->canEdit)->toBeTrue()->and($row->canDelete)->toBeTrue();
    }

    foreach ([new Actor('f', [Role::Finance]), new Actor('r', [Role::Receiver]), Actor::memberOf('m', 'h1'), $this->bakerActor] as $actor) {
        expect(fn () => ($this->world->listProductsOfProducer)($actor, $this->baker))->toThrow(AuthorizationException::class);
    }
});

it('locks a weekly product before checking its unsettled-line edit guard (R1, S5)', function () {
    $id = ($this->world->createProduct)($this->bakerActor, $this->baker, 'Bread', 'loaf', 350, false, null);
    ($this->world->updateProduct)($this->bakerActor, $id, 'Bread', 'loaf', 350, false, null);
    expect($this->world->products->locked)->toContain($id);
});
