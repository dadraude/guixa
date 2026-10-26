<?php

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\CreateStockProduct;
use Guixa\Stock\Application\DeactivateStockProduct;
use Guixa\Stock\Application\ListStockProducts;
use Guixa\Stock\Application\UpdateStockProduct;
use Guixa\Stock\Domain\Error\InvalidContainersPerUnit;
use Guixa\Stock\Domain\Error\InvalidPrice;
use Guixa\Stock\Domain\Error\NameRequired;
use Guixa\Stock\Domain\Error\StockProductNotFound;
use Guixa\Stock\Domain\Error\UnitRequired;
use Tests\Support\InMemory\InMemoryStockProductRepository;

beforeEach(function () {
    $this->products = new InMemoryStockProductRepository;
    $this->manager = Actor::manager('manager-account');
    $this->create = new CreateStockProduct($this->products);
    $this->update = new UpdateStockProduct($this->products);
    $this->deactivate = new DeactivateStockProduct($this->products);
    $this->list = new ListStockProducts($this->products);
});

it('creates a stock product with its price and containers per unit (R25)', function () {
    $id = ($this->create)($this->manager, 'Honey jar', 'jar', 650, false, '2.000');

    $product = ($this->list)($this->manager)[0];

    expect($product->id)->toBe($id)
        ->and($product->name)->toBe('Honey jar')
        ->and($product->unit)->toBe('jar')
        ->and($product->priceCents)->toBe(650)
        ->and($product->variable)->toBeFalse()
        ->and($product->containersPerUnit)->toBe('2.000')
        ->and($product->active)->toBeTrue();
});

it('accepts a price of zero and no containers (R25)', function () {
    ($this->create)($this->manager, 'Free sample', 'piece', 0, false, null);

    $product = ($this->list)($this->manager)[0];

    expect($product->priceCents)->toBe(0)->and($product->containersPerUnit)->toBeNull();
});

it('rejects a negative price (R25)', function () {
    expect(fn () => ($this->create)($this->manager, 'Honey', 'jar', -1, false, null))
        ->toThrow(InvalidPrice::class);
    expect(($this->list)($this->manager))->toBe([]);
});

it('rejects zero containers per unit (R25, R1)', function () {
    expect(fn () => ($this->create)($this->manager, 'Honey', 'jar', 100, false, '0.000'))
        ->toThrow(InvalidContainersPerUnit::class);
});

it('rejects containers per unit that are not a valid quantity (R25, R1)', function () {
    expect(fn () => ($this->create)($this->manager, 'Honey', 'jar', 100, false, '1.2345'))
        ->toThrow(InvalidContainersPerUnit::class);
});

it('requires a name and a unit (R25)', function () {
    expect(fn () => ($this->create)($this->manager, '  ', 'jar', 100, false, null))->toThrow(NameRequired::class);
    expect(fn () => ($this->create)($this->manager, 'Honey', '', 100, false, null))->toThrow(UnitRequired::class);
});

it('edits every field of a stock product (R25)', function () {
    $id = ($this->create)($this->manager, 'Honey', 'jar', 650, false, '2.000');

    ($this->update)($this->manager, $id, 'Wild honey', 'kg', 1200, true, null);

    $product = ($this->list)($this->manager)[0];
    expect($product->name)->toBe('Wild honey')
        ->and($product->unit)->toBe('kg')
        ->and($product->priceCents)->toBe(1200)
        ->and($product->variable)->toBeTrue()
        ->and($product->containersPerUnit)->toBeNull();
});

it('validates the same rules when editing (R25)', function () {
    $id = ($this->create)($this->manager, 'Honey', 'jar', 650, false, null);

    expect(fn () => ($this->update)($this->manager, $id, 'Honey', 'jar', -5, false, null))
        ->toThrow(InvalidPrice::class);
    expect(($this->list)($this->manager)[0]->priceCents)->toBe(650);
});

it('deactivates a stock product and keeps it listed as inactive (R25)', function () {
    $id = ($this->create)($this->manager, 'Honey', 'jar', 650, false, null);

    ($this->deactivate)($this->manager, $id);

    expect(($this->list)($this->manager)[0]->active)->toBeFalse();
});

it('fails on an unknown stock product', function () {
    expect(fn () => ($this->update)($this->manager, 'missing', 'Honey', 'jar', 100, false, null))
        ->toThrow(StockProductNotFound::class);
    expect(fn () => ($this->deactivate)($this->manager, 'missing'))->toThrow(StockProductNotFound::class);
});

it('lists stock products by name', function () {
    ($this->create)($this->manager, 'Oil', 'bottle', 900, false, null);
    ($this->create)($this->manager, 'Honey', 'jar', 650, false, null);

    $names = array_map(fn ($product) => $product->name, ($this->list)($this->manager));

    expect($names)->toBe(['Honey', 'Oil']);
});

it('only lets a manager manage stock products (R25, R36a)', function (Role $role) {
    $actor = new Actor('other-account', [$role], $role === Role::Member ? 'h1' : null);
    $id = ($this->create)($this->manager, 'Honey', 'jar', 650, false, null);

    expect(fn () => ($this->create)($actor, 'Oil', 'bottle', 900, false, null))->toThrow(NotAllowed::class);
    expect(fn () => ($this->update)($actor, $id, 'Oil', 'bottle', 900, false, null))->toThrow(NotAllowed::class);
    expect(fn () => ($this->deactivate)($actor, $id))->toThrow(NotAllowed::class);
    expect(fn () => ($this->list)($actor))->toThrow(NotAllowed::class);
})->with([Role::Member, Role::Producer]);
