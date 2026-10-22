<?php

use Guixa\Shared\Domain\Role;
use Guixa\Stock\Infrastructure\Persistence\StockProductModel;
use Illuminate\Support\Facades\DB;
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

it('keeps the stock movement pages to managers (R26, R27)', function (string $method, string $uri) {
    $product = honey();
    $uri = str_replace('{id}', $product->id, $uri);

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->{$method}($uri)->assertForbidden();
})->with([
    ['get', '/manage/stock/{id}/movements'],
    ['post', '/manage/stock/{id}/entries'],
    ['post', '/manage/stock/{id}/adjustments'],
]);

it('lists the available quantity next to each stock product (R28)', function () {
    $product = honey();
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->post("/manage/stock/{$product->id}/entries", ['quantity' => '10']);

    $this->actingAs($manager)->get('/manage/stock')
        ->assertInertia(fn (Assert $page) => $page->where('products.0.available', '10.000'));
});

it('records an entry and shows the new available quantity (R26)', function () {
    $product = honey();
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)
        ->post("/manage/stock/{$product->id}/entries", ['quantity' => '2,5', 'note' => 'bought from X'])
        ->assertRedirect("/manage/stock/{$product->id}/movements");

    $this->actingAs($manager)->get("/manage/stock/{$product->id}/movements")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Stock/Movements')
            ->where('product.id', $product->id)
            ->where('product.available', '2.500'));
    expect(DB::table('stock_movements')->where('note', 'bought from X')->value('recorded_by'))->toBe($manager->id);
});

it('records an adjustment and rejects one below zero with the domain error (S23, R27)', function () {
    $product = honey();
    $manager = accountWith(Role::Manager);
    $this->actingAs($manager)->post("/manage/stock/{$product->id}/entries", ['quantity' => '10']);

    $this->actingAs($manager)
        ->post("/manage/stock/{$product->id}/adjustments", ['quantity' => '-1', 'note' => 'broken jar'])
        ->assertRedirect("/manage/stock/{$product->id}/movements");
    $this->actingAs($manager)
        ->from("/manage/stock/{$product->id}/movements")
        ->post("/manage/stock/{$product->id}/adjustments", ['quantity' => '-10', 'note' => 'inventory count'])
        ->assertRedirect("/manage/stock/{$product->id}/movements")
        ->assertSessionHasErrors('domain');

    expect(DB::table('stock_movements')->count())->toBe(2);
});

it('rejects malformed quantities and a missing adjustment note before the use case', function () {
    $product = honey();
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->post("/manage/stock/{$product->id}/entries", ['quantity' => '-1'])->assertSessionHasErrors('quantity');
    $this->actingAs($manager)->post("/manage/stock/{$product->id}/adjustments", ['quantity' => 'abc', 'note' => 'x'])->assertSessionHasErrors('quantity');
    $this->actingAs($manager)->post("/manage/stock/{$product->id}/adjustments", ['quantity' => '-1'])->assertSessionHasErrors('note');
});

it('answers 404 to the movements of an unknown stock product', function () {
    $this->actingAs(accountWith(Role::Manager))->get('/manage/stock/'.fake()->uuid().'/movements')->assertNotFound();
});
