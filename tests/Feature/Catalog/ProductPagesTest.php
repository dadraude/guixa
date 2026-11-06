<?php

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

function catalogProducer(string $name = 'Baker'): ProducerModel
{
    return ProducerModel::query()->create([
        'name' => $name, 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
    ]);
}

function accountOfProducer(ProducerModel $producer): User
{
    $user = User::factory()->create(['producer_id' => $producer->id]);
    DB::table('account_roles')->insert(['user_id' => $user->id, 'role' => Role::Producer->value]);

    return $user;
}

function productOf(ProducerModel $producer, array $overrides = []): ProductModel
{
    return ProductModel::query()->create([
        'producer_id' => $producer->id, 'name' => 'Bread', 'unit' => 'loaf', 'price_cents' => 350, 'variable' => false,
        'containers_per_unit' => '2.000', 'active' => true, ...$overrides,
    ]);
}

it('keeps the product pages to producers', function (string $method, string $uri) {
    $product = productOf(catalogProducer());
    $uri = str_replace('{id}', $product->id, $uri);

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Manager))->{$method}($uri)->assertForbidden();
})->with([
    ['get', '/producer/products'],
    ['get', '/producer/products/create'],
    ['post', '/producer/products'],
    ['get', '/producer/products/{id}/edit'],
    ['put', '/producer/products/{id}'],
    ['delete', '/producer/products/{id}'],
    ['put', '/producer/products/{id}/reactivation'],
]);

it('refuses a producer role not linked to a producer', function () {
    $this->actingAs(accountWith(Role::Producer))->get('/producer/products')->assertForbidden();
});

it('lists only the own products with the price as a decimal string (R1, R2, S57)', function () {
    $baker = catalogProducer();
    productOf($baker);
    productOf(catalogProducer('Cheesemaker'), ['name' => 'Cheese']);

    $this->actingAs(accountOfProducer($baker))->get('/producer/products')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Producer/Products/Index')
            ->has('products', 1)
            ->where('products.0.name', 'Bread')
            ->where('products.0.price', '3.50')
            ->where('products.0.containersPerUnit', '2.000')
            ->where('products.0.active', true));
});

it('serializes weekly producer products as a list when stock sorts between them (R2, S66)', function () {
    $producer = catalogProducer();
    productOf($producer, ['name' => 'Apple stock', 'stock' => true]);
    productOf($producer, ['name' => 'Banana weekly']);
    productOf($producer, ['name' => 'Cherry stock', 'stock' => true]);
    productOf($producer, ['name' => 'Date weekly']);

    $this->actingAs(accountOfProducer($producer))->get('/producer/products')->assertOk();
    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion()])
        ->get('/producer/products');

    $response->assertOk();
    expect($response->json('component'))->toBe('Producer/Products/Index')
        ->and($response->json('props.products'))->toBeArray()
        ->and(array_is_list($response->json('props.products')))->toBeTrue()
        ->and(array_column($response->json('props.products'), 'name'))->toBe(['Banana weekly', 'Date weekly']);
});

it('creates a product for the signed-in producer and redirects to the list (R1)', function () {
    $baker = catalogProducer();

    $this->actingAs(accountOfProducer($baker))
        ->post('/producer/products', [
            'name' => 'Bread', 'unit' => 'loaf', 'price' => '3,50', 'variable' => false, 'containers_per_unit' => '2',
        ])
        ->assertRedirect('/producer/products');

    $product = ProductModel::query()->firstOrFail();
    expect($product->producer_id)->toBe($baker->id)
        ->and($product->price_cents)->toBe(350)
        ->and($product->containers_per_unit)->toBe('2.000')
        ->and($product->active)->toBeTrue();
});

it('shows the domain error when the price is negative (R1)', function () {
    $this->actingAs(accountOfProducer(catalogProducer()))
        ->from('/producer/products/create')
        ->post('/producer/products', ['name' => 'Bread', 'unit' => 'loaf', 'price' => '-1.00', 'variable' => false])
        ->assertRedirect('/producer/products/create')
        ->assertSessionHasErrors('domain');

    expect(ProductModel::query()->count())->toBe(0);
});

it('rejects a malformed price or containers per unit before the use case', function (array $input, string $field) {
    $this->actingAs(accountOfProducer(catalogProducer()))
        ->from('/producer/products/create')
        ->post('/producer/products', ['name' => 'Bread', 'unit' => 'loaf', 'price' => '1', 'variable' => false, ...$input])
        ->assertSessionHasErrors($field);
})->with([
    'price' => [['price' => 'abc'], 'price'],
    'containers' => [['containers_per_unit' => 'two'], 'containers_per_unit'],
]);

it('shows the edit form of an own product and 404s on another producer\'s (R2)', function () {
    $baker = catalogProducer();
    $own = productOf($baker);
    $other = productOf(catalogProducer('Cheesemaker'), ['name' => 'Cheese']);
    $account = accountOfProducer($baker);

    $this->actingAs($account)->get("/producer/products/{$own->id}/edit")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Producer/Products/Edit')
            ->where('product.id', $own->id)
            ->where('product.price', '3.50'));
    $this->actingAs($account)->get("/producer/products/{$other->id}/edit")->assertNotFound();
});

it('updates an own product (R1)', function () {
    $baker = catalogProducer();
    $product = productOf($baker);

    $this->actingAs(accountOfProducer($baker))
        ->put("/producer/products/{$product->id}", [
            'name' => 'Rye bread', 'unit' => 'kg', 'price' => '12', 'variable' => true, 'containers_per_unit' => null,
        ])
        ->assertRedirect('/producer/products');

    $product->refresh();
    expect($product->name)->toBe('Rye bread')
        ->and($product->unit)->toBe('kg')
        ->and($product->price_cents)->toBe(1200)
        ->and($product->variable)->toBeTrue()
        ->and($product->containers_per_unit)->toBeNull();
});

it('deactivates and reactivates an own product (R1)', function () {
    $baker = catalogProducer();
    $product = productOf($baker);
    $account = accountOfProducer($baker);

    $this->actingAs($account)->delete("/producer/products/{$product->id}")->assertRedirect('/producer/products');
    expect($product->refresh()->active)->toBeFalse();

    $this->actingAs($account)->put("/producer/products/{$product->id}/reactivation")->assertRedirect('/producer/products');
    expect($product->refresh()->active)->toBeTrue();
});

it('forbids editing, deactivating or reactivating another producer\'s product (R2)', function () {
    $other = productOf(catalogProducer('Cheesemaker'), ['name' => 'Cheese']);
    $account = accountOfProducer(catalogProducer());

    $this->actingAs($account)->put("/producer/products/{$other->id}", [
        'name' => 'Stolen', 'unit' => 'kg', 'price' => '1', 'variable' => false,
    ])->assertForbidden();
    $this->actingAs($account)->delete("/producer/products/{$other->id}")->assertForbidden();
    $this->actingAs($account)->put("/producer/products/{$other->id}/reactivation")->assertForbidden();

    $other->refresh();
    expect($other->name)->toBe('Cheese')->and($other->active)->toBeTrue();
});

it('answers 404 to a product id that is not a UUID', function (string $method, string $uri) {
    $this->actingAs(accountOfProducer(catalogProducer()))->{$method}($uri, [
        'name' => 'Bread', 'unit' => 'loaf', 'price' => '1', 'variable' => false,
    ])->assertNotFound();
})->with([
    ['get', '/producer/products/not-a-uuid/edit'],
    ['put', '/producer/products/not-a-uuid'],
    ['delete', '/producer/products/not-a-uuid'],
]);

it('shows the domain error for an unknown product id', function () {
    $this->actingAs(accountOfProducer(catalogProducer()))
        ->put('/producer/products/'.fake()->uuid(), ['name' => 'Bread', 'unit' => 'loaf', 'price' => '1', 'variable' => false])
        ->assertSessionHasErrors('domain');
});

it('keeps the unit and the variable flag while the product has lines in a cycle that is not settled, and lets the rest change (R1, S5)', function () {
    $baker = catalogProducer();
    $product = productOf($baker);
    $cycleId = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $cycleId, 'pickup_date' => '2026-11-07']);
    $household = linkToNewHousehold($account = accountOfProducer($baker));
    $orderId = (string) Str::uuid7();
    DB::table('orders')->insert(['id' => $orderId, 'cycle_id' => $cycleId, 'household_id' => $household]);
    DB::table('order_lines')->insert([
        'id' => (string) Str::uuid7(), 'order_id' => $orderId, 'product_id' => $product->id, 'producer_id' => $baker->id,
        'product_name' => 'Bread', 'unit' => 'loaf', 'unit_price_cents' => 350, 'variable' => false,
        'ordered_quantity' => '1', 'allocated_quantity' => '1', 'created_at' => now(), 'added_by' => $account->id,
    ]);

    $this->actingAs($account)
        ->from("/producer/products/{$product->id}/edit")
        ->put("/producer/products/{$product->id}", ['name' => 'Bread', 'unit' => 'kg', 'price' => '3.50', 'variable' => false, 'containers_per_unit' => '2'])
        ->assertSessionHasErrors('domain');
    expect($product->refresh()->unit)->toBe('loaf');

    $this->actingAs($account)
        ->put("/producer/products/{$product->id}", ['name' => 'Rye bread', 'unit' => 'loaf', 'price' => '4.00', 'variable' => false, 'containers_per_unit' => '2'])
        ->assertRedirect('/producer/products');
    expect($product->refresh()->name)->toBe('Rye bread');

    DB::table('cycles')->where('id', $cycleId)->update(['settled_at' => now()]);

    $this->actingAs($account)
        ->put("/producer/products/{$product->id}", ['name' => 'Rye bread', 'unit' => 'kg', 'price' => '4.00', 'variable' => true, 'containers_per_unit' => null])
        ->assertRedirect('/producer/products');
    expect($product->refresh()->unit)->toBe('kg');
});
