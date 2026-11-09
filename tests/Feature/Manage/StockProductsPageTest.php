<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function stockProducer(): ProducerModel
{
    return ProducerModel::query()->create(['name' => 'Beekeeper', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
}

function honey(array $overrides = []): ProductModel
{
    return ProductModel::query()->create([
        'producer_id' => stockProducer()->id, 'stock' => true,
        'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 650, 'variable' => false,
        'containers_per_unit' => '2.000', 'active' => true, ...$overrides,
    ]);
}

function stockCreatePath(ProducerModel $producer): string
{
    return "/catalogue/producers/{$producer->id}/products/stock";
}

function stockProductsPage(ProducerModel|ProductModel $of): string
{
    return '/catalogue/producers/'.($of instanceof ProductModel ? $of->producer_id : $of->id).'/products';
}

function stockProductPath(ProductModel $product): string
{
    return "/catalogue/producers/{$product->producer_id}/products/stock/{$product->id}";
}

it('records inventory against the same Catalog product created for a producer (R1a, R26, S71)', function () {
    $producer = ProducerModel::query()->create(['name' => 'Beekeeper', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $product = ProductModel::query()->create([
        'producer_id' => $producer->id, 'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 650,
        'variable' => false, 'active' => true, 'stock' => true,
    ]);

    $this->actingAs(accountWith(Role::Manager))
        ->post("/manage/stock/{$product->id}/entries", ['quantity' => '5'])
        ->assertRedirect("/manage/stock/{$product->id}/movements");

    expect(DB::table('stock_movements')->where('product_id', $product->id)->count())->toBe(1);
});

it('rejects weekly Catalog IDs at inventory entry and adjustment without movements (R26, R27, S76)', function () {
    $product = ProductModel::query()->create([
        'producer_id' => stockProducer()->id, 'name' => 'Weekly oil', 'unit' => 'bottle',
        'price_cents' => 650, 'variable' => false, 'active' => true, 'stock' => false,
    ]);
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->from('/manage/stock')->post("/manage/stock/{$product->id}/entries", ['quantity' => '5'])
        ->assertSessionHasErrors('domain');
    $this->actingAs($manager)->from('/manage/stock')->post("/manage/stock/{$product->id}/adjustments", ['quantity' => '1', 'note' => 'count'])
        ->assertSessionHasErrors('domain');
    expect(DB::table('stock_movements')->count())->toBe(0);
});

it('creates stock from the producer record and shows that Catalog ID in stock administration (S65, S71)', function () {
    $producer = ProducerModel::query()->create(['name' => 'Beekeeper', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $other = ProducerModel::query()->create(['name' => 'Oil producer', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->get("/catalogue/producers/{$producer->id}/products/stock/create")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Manage/Stock/Create')
            ->where('producer.id', $producer->id)->where('producer.name', 'Beekeeper'));
    $this->actingAs($manager)->post("/catalogue/producers/{$producer->id}/products/stock", [
        'producer_id' => $other->id, 'name' => 'Honey', 'unit' => 'jar', 'price' => '6.50', 'variable' => false,
    ])->assertRedirect(stockProductsPage($producer));

    $product = ProductModel::query()->where('name', 'Honey')->firstOrFail();
    expect($product->producer_id)->toBe($producer->id)->and($product->stock)->toBeTrue();
    $this->actingAs($manager)->get('/manage/stock')->assertInertia(fn (Assert $page) => $page
        ->component('Manage/Stock/Index')->where('products.0.id', $product->id));
});

it('edits stock only through its supplying producer context (R1a, S65, S71)', function () {
    $product = honey();
    $other = stockProducer();
    $manager = accountWith(Role::Manager);
    $own = "/catalogue/producers/{$product->producer_id}/products/stock/{$product->id}";
    $wrong = "/catalogue/producers/{$other->id}/products/stock/{$product->id}";

    $this->actingAs($manager)->get($own.'/edit')->assertOk();
    $this->actingAs($manager)->get($wrong.'/edit')->assertNotFound();
    $this->actingAs($manager)->from($wrong.'/edit')
        ->put($wrong, ['name' => 'Moved', 'unit' => 'jar', 'price' => '6.50', 'variable' => false])
        ->assertRedirect($wrong.'/edit')->assertSessionHasErrors('domain');
    expect($product->refresh()->producer_id)->toBe($product->producer_id)
        ->and($product->name)->toBe('Honey');
});

it('keeps the stock pages to managers', function (string $method, string $uri) {
    $product = honey();
    $uri = str_replace('{id}', $product->id, $uri);
    $uri = str_replace('{producer}', $product->producer_id, $uri);

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->{$method}($uri)->assertForbidden();
})->with([
    ['get', '/manage/stock'],
    ['get', '/catalogue/producers/{producer}/products/stock/create'],
    ['post', '/catalogue/producers/{producer}/products/stock'],
    ['get', '/catalogue/producers/{producer}/products/stock/{id}/edit'],
    ['put', '/catalogue/producers/{producer}/products/stock/{id}'],
    ['delete', '/catalogue/producers/{producer}/products/stock/{id}'],
    ['put', '/manage/stock/{id}/available'],
    ['put', '/catalogue/producers/{producer}/products/stock/{id}/reactivation'],
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
    $producer = stockProducer();
    $this->actingAs(accountWith(Role::Manager))
        ->post(stockCreatePath($producer), [
            'name' => 'Honey', 'unit' => 'jar', 'price' => '6.50', 'variable' => false, 'containers_per_unit' => '2',
        ])
        ->assertRedirect(stockProductsPage($producer));

    $product = ProductModel::query()->firstOrFail();
    expect($product->price_cents)->toBe(650)
        ->and($product->containers_per_unit)->toBe('2.000')
        ->and($product->active)->toBeTrue();
});

it('accepts a comma as the decimal separator and a free price', function () {
    $producer = stockProducer();
    $this->actingAs(accountWith(Role::Manager))
        ->post(stockCreatePath($producer), ['name' => 'Sample', 'unit' => 'piece', 'price' => '0', 'variable' => true])
        ->assertRedirect(stockProductsPage($producer));
    $this->actingAs(accountWith(Role::Manager))
        ->post(stockCreatePath($producer), ['name' => 'Oil', 'unit' => 'l', 'price' => '9,5', 'variable' => true])
        ->assertRedirect(stockProductsPage($producer));

    expect(ProductModel::query()->orderBy('name')->pluck('price_cents')->all())->toBe([950, 0]);
});

it('shows the domain error when the price is negative (R25)', function () {
    $producer = stockProducer();
    $createPath = stockCreatePath($producer);
    $this->actingAs(accountWith(Role::Manager))
        ->from($createPath.'/create')
        ->post($createPath, ['name' => 'Honey', 'unit' => 'jar', 'price' => '-1.00', 'variable' => false])
        ->assertRedirect($createPath.'/create')
        ->assertSessionHasErrors('domain');

    expect(ProductModel::query()->count())->toBe(0);
});

it('rejects a malformed price before reaching the use case', function () {
    $producer = stockProducer();
    $createPath = stockCreatePath($producer);
    $this->actingAs(accountWith(Role::Manager))
        ->from($createPath.'/create')
        ->post($createPath, ['name' => 'Honey', 'unit' => 'jar', 'price' => 'abc', 'variable' => false])
        ->assertSessionHasErrors('price');
});

it('shows the edit form of a stock product', function () {
    $product = honey();

    $this->actingAs(accountWith(Role::Manager))->get(stockProductPath($product).'/edit')
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
        ->put(stockProductPath($product), [
            'name' => 'Wild honey', 'unit' => 'kg', 'price' => '12', 'variable' => true, 'containers_per_unit' => null,
        ])
        ->assertRedirect(stockProductsPage($product));

    $product->refresh();
    expect($product->name)->toBe('Wild honey')
        ->and($product->price_cents)->toBe(1200)
        ->and($product->variable)->toBeTrue()
        ->and($product->containers_per_unit)->toBeNull();
});

it('deactivates a stock product (R25)', function () {
    $product = honey();

    $this->actingAs(accountWith(Role::Manager))->delete(stockProductPath($product))->assertRedirect(stockProductsPage($product));

    expect($product->refresh()->active)->toBeFalse();
});

it('reactivates a stock product from the manager list', function () {
    $product = honey(['active' => false]);

    $this->actingAs(accountWith(Role::Manager))
        ->put(stockProductPath($product).'/reactivation')
        ->assertRedirect(stockProductsPage($product));

    expect($product->refresh()->active)->toBeTrue();
});

it('sets an absolute stock count through an audited adjustment', function () {
    $product = honey();
    $manager = accountWith(Role::Manager);
    $this->actingAs($manager)->post("/manage/stock/{$product->id}/entries", ['quantity' => '10']);

    $this->actingAs($manager)->put("/manage/stock/{$product->id}/available", ['available' => '8,5'])
        ->assertRedirect('/manage/stock');
    $this->actingAs($manager)->get('/manage/stock')
        ->assertInertia(fn (Assert $page) => $page->where('products.0.available', '8.500'));
    expect(DB::table('stock_movements')->count())->toBe(2)
        ->and(DB::table('stock_movements')->where('note', 'Recompte d’estoc')->count())->toBe(1);

    $this->actingAs($manager)->put("/manage/stock/{$product->id}/available", ['available' => '8.500'])
        ->assertRedirect('/manage/stock');
    expect(DB::table('stock_movements')->count())->toBe(2);
});

it('stores the inventory count note in the application locale', function () {
    $product = honey();
    $manager = accountWith(Role::Manager);
    app()->setLocale('en');

    $this->actingAs($manager)->put("/manage/stock/{$product->id}/available", ['available' => '3'])
        ->assertRedirect('/manage/stock');

    expect(DB::table('stock_movements')->where('note', 'Inventory count')->count())->toBe(1);
});

it('answers 404 when setting the count of an unknown product', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->put('/manage/stock/'.Str::uuid7().'/available', ['available' => '3'])
        ->assertNotFound();
});

it('rejects an invalid absolute stock count', function () {
    $product = honey();
    $this->actingAs(accountWith(Role::Manager))
        ->put("/manage/stock/{$product->id}/available", ['available' => '-1'])
        ->assertSessionHasErrors('available');
});

it('answers 404 to a stock product id that is not a UUID', function (string $method, string $uri) {
    $uri = str_replace('{producer}', stockProducer()->id, $uri);
    $this->actingAs(accountWith(Role::Manager))->{$method}($uri, [
        'name' => 'Honey', 'unit' => 'jar', 'price' => '1', 'variable' => false,
    ])->assertNotFound();
})->with([
    ['get', '/catalogue/producers/{producer}/products/stock/not-a-uuid/edit'],
    ['put', '/catalogue/producers/{producer}/products/stock/not-a-uuid'],
    ['delete', '/catalogue/producers/{producer}/products/stock/not-a-uuid'],
    ['put', '/manage/stock/not-a-uuid/available'],
    ['put', '/catalogue/producers/{producer}/products/stock/not-a-uuid/reactivation'],
]);

it('accepts a comma as the decimal separator of containers per unit', function () {
    $producer = stockProducer();
    $this->actingAs(accountWith(Role::Manager))
        ->post(stockCreatePath($producer), [
            'name' => 'Honey', 'unit' => 'jar', 'price' => '1', 'variable' => false, 'containers_per_unit' => '2,5',
        ])
        ->assertRedirect(stockProductsPage($producer));

    expect(ProductModel::query()->firstOrFail()->containers_per_unit)->toBe('2.500');
});

it('shows a malformed containers per unit under its field', function () {
    $producer = stockProducer();
    $createPath = stockCreatePath($producer);
    $this->actingAs(accountWith(Role::Manager))
        ->from($createPath.'/create')
        ->post($createPath, [
            'name' => 'Honey', 'unit' => 'jar', 'price' => '1', 'variable' => false, 'containers_per_unit' => 'two',
        ])
        ->assertSessionHasErrors('containers_per_unit');

    expect(ProductModel::query()->count())->toBe(0);
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

it('offers the cycles on the movements page so an adjustment can name one (R27)', function () {
    $product = honey();
    $first = (string) fake()->uuid();
    $second = (string) fake()->uuid();
    DB::table('cycles')->insert([
        ['id' => $second, 'pickup_date' => '2026-11-14'],
        ['id' => $first, 'pickup_date' => '2026-11-07'],
    ]);

    $this->actingAs(accountWith(Role::Manager))->get("/manage/stock/{$product->id}/movements")
        ->assertInertia(fn (Assert $page) => $page
            ->has('cycles', 2)
            ->where('cycles.0.id', $first)
            ->where('cycles.0.pickupDate', '2026-11-07')
            ->where('cycles.1.id', $second));
});

it('records the cycle an adjustment refers to, and none when left empty (R27)', function () {
    $product = honey();
    $manager = accountWith(Role::Manager);
    $cycleId = (string) fake()->uuid();
    DB::table('cycles')->insert(['id' => $cycleId, 'pickup_date' => '2026-11-07']);
    $this->actingAs($manager)->post("/manage/stock/{$product->id}/entries", ['quantity' => '10']);

    $this->actingAs($manager)
        ->post("/manage/stock/{$product->id}/adjustments", ['quantity' => '-1', 'note' => 'broken jar', 'cycle_id' => $cycleId])
        ->assertRedirect("/manage/stock/{$product->id}/movements");
    $this->actingAs($manager)
        ->post("/manage/stock/{$product->id}/adjustments", ['quantity' => '-1', 'note' => 'inventory count', 'cycle_id' => ''])
        ->assertRedirect("/manage/stock/{$product->id}/movements");

    expect(DB::table('stock_movements')->where('note', 'broken jar')->value('cycle_id'))->toBe($cycleId)
        ->and(DB::table('stock_movements')->where('note', 'inventory count')->value('cycle_id'))->toBeNull();
});

it('rejects an adjustment that names a cycle that does not exist (R27)', function () {
    $product = honey();
    $manager = accountWith(Role::Manager);
    $this->actingAs($manager)->post("/manage/stock/{$product->id}/entries", ['quantity' => '10']);

    $this->actingAs($manager)
        ->post("/manage/stock/{$product->id}/adjustments", ['quantity' => '-1', 'note' => 'x', 'cycle_id' => (string) fake()->uuid()])
        ->assertSessionHasErrors('cycle_id');

    expect(DB::table('stock_movements')->count())->toBe(1);
});

it('shows permanent stock unit protection after a movement and still accepts metadata edits (R25a, S72)', function () {
    $product = honey();
    $manager = accountWith(Role::Manager);
    $this->actingAs($manager)->post("/manage/stock/{$product->id}/entries", ['quantity' => '1'])->assertRedirect();
    $this->actingAs($manager)->get(stockProductPath($product).'/edit')
        ->assertInertia(fn (Assert $page) => $page->component('Manage/Stock/Edit')->where('used', true));

    $this->actingAs($manager)->from(stockProductPath($product).'/edit')->put(stockProductPath($product), [
        'name' => 'Raw honey', 'unit' => 'kg', 'price' => '7.00', 'variable' => false,
    ])->assertSessionHasErrors('domain');
    expect($product->refresh()->unit)->toBe('jar');
    $this->actingAs($manager)->put(stockProductPath($product), [
        'name' => 'Raw honey', 'unit' => 'jar', 'price' => '7.00', 'variable' => false, 'containers_per_unit' => '3',
    ])->assertRedirect(stockProductsPage($product));
    expect($product->refresh()->name)->toBe('Raw honey')
        ->and($product->price_cents)->toBe(700)
        ->and($product->containers_per_unit)->toBe('3.000');
});

it('deletes an unused stock record but keeps one with a movement (R1c, S68, S70)', function () {
    $unused = honey();
    $used = honey();
    $manager = accountWith(Role::Manager);
    $this->actingAs($manager)->post("/manage/stock/{$used->id}/entries", ['quantity' => '1'])->assertRedirect();

    $this->actingAs($manager)->from(stockProductPath($used).'/edit')
        ->delete(stockProductPath($used).'/unused')->assertSessionHasErrors('domain');
    $this->actingAs($manager)->delete(stockProductPath($unused).'/unused')
        ->assertRedirect("/catalogue/producers/{$unused->producer_id}/products");
    expect(ProductModel::query()->whereKey($used->id)->exists())->toBeTrue()
        ->and(ProductModel::query()->whereKey($unused->id)->exists())->toBeFalse();
});
