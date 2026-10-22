<?php

use Guixa\Shared\Domain\Role;
use Guixa\Stock\Infrastructure\Persistence\StockProductModel;
use Inertia\Testing\AssertableInertia as Assert;

function honey(array $overrides = []): StockProductModel
{
    return StockProductModel::query()->create([
        'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 650, 'variable' => false,
        'containers_per_unit' => '2.000', 'active' => true, ...$overrides,
    ]);
}

it('keeps the stock pages to managers', function (string $method, string $uri) {
    $product = honey();
    $uri = str_replace('{id}', $product->id, $uri);

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->{$method}($uri)->assertForbidden();
})->with([
    ['get', '/manage/stock'],
    ['get', '/manage/stock/create'],
    ['post', '/manage/stock'],
    ['get', '/manage/stock/{id}/edit'],
    ['put', '/manage/stock/{id}'],
    ['delete', '/manage/stock/{id}'],
]);

it('lists the stock products with their price as a decimal string (R25)', function () {
    honey();

    $this->actingAs(accountWith(Role::Manager))->get('/manage/stock')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Stock/Index')
            ->has('products', 1)
            ->where('products.0.name', 'Honey')
            ->where('products.0.price', '6.50')
            ->where('products.0.containersPerUnit', '2.000')
            ->where('products.0.active', true));
});

it('creates a stock product from euros and redirects to the list (R25)', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/stock', [
            'name' => 'Honey', 'unit' => 'jar', 'price' => '6.50', 'variable' => false, 'containers_per_unit' => '2',
        ])
        ->assertRedirect('/manage/stock');

    $product = StockProductModel::query()->firstOrFail();
    expect($product->price_cents)->toBe(650)
        ->and($product->containers_per_unit)->toBe('2.000')
        ->and($product->active)->toBeTrue();
});

it('accepts a comma as the decimal separator and a free price', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/stock', ['name' => 'Sample', 'unit' => 'piece', 'price' => '0', 'variable' => true])
        ->assertRedirect('/manage/stock');
    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/stock', ['name' => 'Oil', 'unit' => 'l', 'price' => '9,5', 'variable' => true])
        ->assertRedirect('/manage/stock');

    expect(StockProductModel::query()->orderBy('name')->pluck('price_cents')->all())->toBe([950, 0]);
});

it('shows the domain error when the price is negative (R25)', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->from('/manage/stock/create')
        ->post('/manage/stock', ['name' => 'Honey', 'unit' => 'jar', 'price' => '-1.00', 'variable' => false])
        ->assertRedirect('/manage/stock/create')
        ->assertSessionHasErrors('domain');

    expect(StockProductModel::query()->count())->toBe(0);
});

it('rejects a malformed price before reaching the use case', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->from('/manage/stock/create')
        ->post('/manage/stock', ['name' => 'Honey', 'unit' => 'jar', 'price' => 'abc', 'variable' => false])
        ->assertSessionHasErrors('price');
});

it('shows the edit form of a stock product', function () {
    $product = honey();

    $this->actingAs(accountWith(Role::Manager))->get("/manage/stock/{$product->id}/edit")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Stock/Edit')
            ->where('product.id', $product->id)
            ->where('product.price', '6.50'));
});

it('answers 404 to the edit form of an unknown stock product', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->get('/manage/stock/'.fake()->uuid().'/edit')
        ->assertNotFound();
});

it('updates a stock product (R25)', function () {
    $product = honey();

    $this->actingAs(accountWith(Role::Manager))
        ->put("/manage/stock/{$product->id}", [
            'name' => 'Wild honey', 'unit' => 'kg', 'price' => '12', 'variable' => true, 'containers_per_unit' => null,
        ])
        ->assertRedirect('/manage/stock');

    $product->refresh();
    expect($product->name)->toBe('Wild honey')
        ->and($product->price_cents)->toBe(1200)
        ->and($product->variable)->toBeTrue()
        ->and($product->containers_per_unit)->toBeNull();
});

it('deactivates a stock product (R25)', function () {
    $product = honey();

    $this->actingAs(accountWith(Role::Manager))->delete("/manage/stock/{$product->id}")->assertRedirect('/manage/stock');

    expect($product->refresh()->active)->toBeFalse();
});

it('answers 404 to a stock product id that is not a UUID', function (string $method, string $uri) {
    $this->actingAs(accountWith(Role::Manager))->{$method}($uri, [
        'name' => 'Honey', 'unit' => 'jar', 'price' => '1', 'variable' => false,
    ])->assertNotFound();
})->with([
    ['get', '/manage/stock/not-a-uuid/edit'],
    ['put', '/manage/stock/not-a-uuid'],
    ['delete', '/manage/stock/not-a-uuid'],
]);

it('accepts a comma as the decimal separator of containers per unit', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/stock', [
            'name' => 'Honey', 'unit' => 'jar', 'price' => '1', 'variable' => false, 'containers_per_unit' => '2,5',
        ])
        ->assertRedirect('/manage/stock');

    expect(StockProductModel::query()->firstOrFail()->containers_per_unit)->toBe('2.500');
});

it('shows a malformed containers per unit under its field', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->from('/manage/stock/create')
        ->post('/manage/stock', [
            'name' => 'Honey', 'unit' => 'jar', 'price' => '1', 'variable' => false, 'containers_per_unit' => 'two',
        ])
        ->assertSessionHasErrors('containers_per_unit');

    expect(StockProductModel::query()->count())->toBe(0);
});
