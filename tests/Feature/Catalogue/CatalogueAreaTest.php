<?php

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function areaProducer(string $name = 'Baker', bool $active = true): ProducerModel
{
    return ProducerModel::query()->create(['name' => $name, 'active' => $active, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
}

function areaProduct(ProducerModel $producer, array $overrides = []): ProductModel
{
    return ProductModel::query()->create([
        'producer_id' => $producer->id, 'name' => 'Tomàquet', 'unit' => 'kg', 'price_cents' => 300, 'variable' => false,
        'active' => true, 'stock' => false, ...$overrides,
    ]);
}

function areaProducerAccount(ProducerModel $producer, Role ...$extra): User
{
    $user = User::factory()->create(['producer_id' => $producer->id]);
    foreach ([Role::Producer, ...$extra] as $role) {
        DB::table('account_roles')->insert(['user_id' => $user->id, 'role' => $role->value]);
    }

    return $user;
}

function areaUnsettledLine(ProducerModel $producer, ProductModel $product): void
{
    $cycleId = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $cycleId, 'pickup_date' => '2026-11-07']);
    $account = User::factory()->create();
    $household = linkToNewHousehold($account);
    $orderId = (string) Str::uuid7();
    DB::table('orders')->insert(['id' => $orderId, 'cycle_id' => $cycleId, 'household_id' => $household]);
    DB::table('order_lines')->insert([
        'id' => (string) Str::uuid7(), 'order_id' => $orderId, 'product_id' => $product->id, 'producer_id' => $producer->id,
        'product_name' => $product->name, 'unit' => $product->unit, 'unit_price_cents' => $product->price_cents, 'variable' => false,
        'ordered_quantity' => '1', 'allocated_quantity' => '1', 'created_at' => now(), 'added_by' => $account->id,
    ]);
}

function weeklyForm(array $overrides = []): array
{
    return ['name' => 'Tomàquet', 'unit' => 'kg', 'price' => '3.00', 'variable' => false, 'containers_per_unit' => null, ...$overrides];
}

// R14 — the Catàleg area is closed to every other role

it('closes every Catàleg route to accounts without Manager or Catalogue (R14)', function (string $method, string $uri, ?Role $role) {
    $producer = areaProducer();
    $product = areaProduct($producer);
    $uri = str_replace(['{producer}', '{product}'], [$producer->id, $product->id], $uri);
    $account = $role === null ? accountWith(Role::Member) : accountWith($role);

    $this->actingAs($account)->{$method}($uri)->assertForbidden();
})->with(function () {
    $routes = [
        ['get', '/catalogue/producers'],
        ['get', '/catalogue/producers/{producer}/products'],
        ['get', '/catalogue/producers/{producer}/products/weekly/create'],
        ['post', '/catalogue/producers/{producer}/products/weekly'],
        ['get', '/catalogue/producers/{producer}/products/weekly/{product}/edit'],
        ['put', '/catalogue/producers/{producer}/products/weekly/{product}'],
        ['delete', '/catalogue/producers/{producer}/products/weekly/{product}'],
        ['delete', '/catalogue/producers/{producer}/products/weekly/{product}/unused'],
        ['put', '/catalogue/producers/{producer}/products/weekly/{product}/reactivation'],
    ];
    foreach ($routes as [$method, $uri]) {
        foreach ([Role::Receiver, Role::Finance, null] as $role) {
            yield "{$method} {$uri} as ".($role?->value ?? 'member') => [$method, $uri, $role];
        }
    }
});

it('sends guests to the login page and moves the producer products page out of /manage (R14)', function () {
    $producer = areaProducer();

    $this->get('/catalogue/producers')->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Manager))->get("/manage/producers/{$producer->id}/products")->assertMethodNotAllowed();
});

// R2, R15, S1 — a Catalogue-only account with no household

it('shows a Catalogue-only account its area in the switch and one dashboard shortcut, with no new card (S1, R2, R15)', function () {
    $account = accountWith(Role::Catalogue);

    $this->actingAs($account)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('Dashboard')
        ->where('roleAreas', [['role' => 'catalogue', 'label' => 'Catalogue', 'href' => '/catalogue/producers']])
        ->where('areaShortcuts', [['role' => 'catalogue', 'label' => 'Catalogue', 'href' => '/catalogue/producers']])
        ->where('isMember', false)->where('isManager', false)
        ->where('managerSummary', null)->where('staffPendingCollections', null)->where('pendingPickups', []));

    $this->actingAs($account)->get('/catalogue/producers')->assertOk();
});

it('gives other roles no Catalogue shortcut (R15)', function () {
    $this->actingAs(accountWith(Role::Manager, Role::Finance))->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('areaShortcuts', []));
    $this->actingAs(accountWith(Role::Manager, Role::Catalogue))->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('areaShortcuts.0.role', 'catalogue')->has('roleAreas', 2));
});

// R5 — the producer list is read-only for Catalogue

it('lists producers, deactivated ones marked, without producer administration (R5)', function () {
    areaProducer('Active baker');
    areaProducer('Closed dairy', active: false);
    $catalogue = accountWith(Role::Catalogue);

    $this->actingAs($catalogue)->get('/catalogue/producers')->assertInertia(fn (Assert $page) => $page
        ->component('Catalogue/Producers/Index')
        ->has('producers', 2)
        ->where('producers.0.name', 'Active baker')->where('producers.0.active', true)
        ->where('producers.1.name', 'Closed dairy')->where('producers.1.active', false)
        ->where('producers.1.accountEmail', null));
});

it('keeps producer administration routes closed to Catalogue (R5)', function (string $method, string $uri) {
    $producer = areaProducer();
    $uri = str_replace('{producer}', $producer->id, $uri);

    $this->actingAs(accountWith(Role::Catalogue))->{$method}($uri)->assertForbidden();
})->with([
    ['get', '/manage/producers'],
    ['post', '/manage/producers'],
    ['post', '/manage/producers/{producer}/account'],
    ['post', '/manage/producers/{producer}/deactivation'],
    ['delete', '/manage/producers/{producer}/deactivation'],
    ['get', '/manage/producers/{producer}/products/create'],
]);

// R3 — the producer's products page with can flags

it('shows both modes with can flags: Catalogue edits weekly products only, Manager keeps stock (R3, R5)', function () {
    $producer = areaProducer();
    $weekly = areaProduct($producer, ['name' => 'Bread']);
    $stock = areaProduct($producer, ['name' => 'Honey', 'stock' => true]);

    $this->actingAs(accountWith(Role::Catalogue))->get("/catalogue/producers/{$producer->id}/products")
        ->assertInertia(fn (Assert $page) => $page->component('Catalogue/Producers/Products')
            ->where('canCreateStock', false)
            ->where('products.0.id', $weekly->id)
            ->where('products.0.can', ['edit' => true, 'changeClassification' => false, 'delete' => true])
            ->where('products.1.id', $stock->id)
            ->where('products.1.can', ['edit' => false, 'changeClassification' => false, 'delete' => false]));

    $this->actingAs(accountWith(Role::Manager))->get("/catalogue/producers/{$producer->id}/products")
        ->assertInertia(fn (Assert $page) => $page->where('canCreateStock', true)
            ->where('products.0.can.edit', true)->where('products.1.can', ['edit' => true, 'changeClassification' => true, 'delete' => true]));
});

it('keeps stock actions on the Manager-only routes until Catalogue administers stock (R3)', function () {
    $producer = areaProducer();
    $stock = areaProduct($producer, ['stock' => true]);
    $catalogue = accountWith(Role::Catalogue);

    $this->actingAs($catalogue)->get("/manage/producers/{$producer->id}/products/create")->assertForbidden();
    $this->actingAs($catalogue)->put("/manage/products/{$stock->id}/classification", ['stock' => false])->assertForbidden();
    $this->actingAs(accountWith(Role::Manager))->put("/manage/products/{$stock->id}/classification", ['stock' => false])
        ->assertRedirect("/catalogue/producers/{$producer->id}/products");
});

it('creates, edits, deactivates, reactivates and deletes a weekly product of any producer (R3)', function (Role $role) {
    $producer = areaProducer();
    $actor = accountWith($role);
    $products = "/catalogue/producers/{$producer->id}/products";

    $this->actingAs($actor)->get("{$products}/weekly/create")->assertInertia(fn (Assert $page) => $page->component('Catalogue/Producers/WeeklyProduct/Create')->where('producer.id', $producer->id));
    $this->actingAs($actor)->post("{$products}/weekly", weeklyForm())->assertRedirect($products);
    $product = ProductModel::query()->where('producer_id', $producer->id)->firstOrFail();
    expect($product->stock)->toBeFalse();

    $this->actingAs($actor)->get("{$products}/weekly/{$product->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->component('Catalogue/Producers/WeeklyProduct/Edit')->where('product.id', $product->id)->where('product.price', '3.00')->where('used', false));
    $this->actingAs($actor)->put("{$products}/weekly/{$product->id}", weeklyForm(['name' => 'Raf', 'price' => '4.50']))->assertRedirect($products);
    expect($product->refresh()->name)->toBe('Raf')->and($product->price_cents)->toBe(450);

    $this->actingAs($actor)->delete("{$products}/weekly/{$product->id}")->assertRedirect($products);
    expect($product->refresh()->active)->toBeFalse();
    $this->actingAs($actor)->put("{$products}/weekly/{$product->id}/reactivation")->assertRedirect($products);
    expect($product->refresh()->active)->toBeTrue();

    $this->actingAs($actor)->delete("{$products}/weekly/{$product->id}/unused")->assertRedirect($products);
    expect(ProductModel::query()->whereKey($product->id)->exists())->toBeFalse();
})->with([Role::Catalogue, Role::Manager]);

it('answers 404 for a missing producer and for a stock product on the weekly edit page (R3)', function () {
    $producer = areaProducer();
    $stock = areaProduct($producer, ['stock' => true]);
    $catalogue = accountWith(Role::Catalogue);

    $this->actingAs($catalogue)->get('/catalogue/producers/'.Str::uuid7().'/products')->assertNotFound();
    $this->actingAs($catalogue)->post('/catalogue/producers/'.Str::uuid7().'/products/weekly', weeklyForm())->assertNotFound();
    $this->actingAs($catalogue)->get("/catalogue/producers/{$producer->id}/products/weekly/{$stock->id}/edit")->assertNotFound();
});

// S4 — price saved, unit rejected, the producer's later edit replaces it

it('saves the price and rejects the unit while lines are unsettled, and the producer\'s later edit replaces it (S4)', function (Role $role) {
    $producer = areaProducer();
    $tomato = areaProduct($producer);
    areaUnsettledLine($producer, $tomato);
    $actor = accountWith($role);
    $url = "/catalogue/producers/{$producer->id}/products/weekly/{$tomato->id}";

    $this->actingAs($actor)->from("{$url}/edit")->put($url, weeklyForm(['price' => '3.80']))
        ->assertRedirect("/catalogue/producers/{$producer->id}/products");
    expect($tomato->refresh()->price_cents)->toBe(380);

    $this->actingAs($actor)->from("{$url}/edit")->put($url, weeklyForm(['unit' => 'box', 'price' => '9.00']))
        ->assertSessionHasErrors('domain');
    expect($tomato->refresh()->unit)->toBe('kg')->and($tomato->price_cents)->toBe(380);

    $this->actingAs(areaProducerAccount($producer))->put("/producer/products/{$tomato->id}", weeklyForm(['price' => '3.10']))
        ->assertRedirect('/producer/products');
    expect($tomato->refresh()->price_cents)->toBe(310);
})->with([Role::Catalogue, Role::Manager]);

// S5 — a producer's account with Catalogue edits others' products only through the role

it('keeps the Producer area to the own products for an account that also holds Catalogue (S5, R3)', function () {
    $p = areaProducer('P');
    $q = areaProducer('Q');
    $own = areaProduct($p, ['name' => 'Own']);
    $other = areaProduct($q, ['name' => 'Other']);
    $both = areaProducerAccount($p, Role::Catalogue);

    $this->actingAs($both)->get('/producer/products')->assertInertia(fn (Assert $page) => $page
        ->has('products', 1)->where('products.0.id', $own->id));
    $this->actingAs($both)->get("/producer/products/{$other->id}/edit")->assertNotFound();
    $this->actingAs($both)->put("/producer/products/{$other->id}", weeklyForm(['price' => '1.00']))->assertForbidden();
    $this->actingAs($both)->delete("/producer/products/{$other->id}")->assertForbidden();
    $this->actingAs($both)->delete("/producer/products/{$other->id}/unused")->assertForbidden();
    $this->actingAs($both)->put("/producer/products/{$other->id}/reactivation")->assertForbidden();
    expect(ProductModel::query()->whereKey($other->id)->value('price_cents'))->toBe(300);

    $this->actingAs($both)->get("/catalogue/producers/{$q->id}/products/weekly/{$other->id}/edit")->assertOk();
    $this->actingAs($both)->put("/catalogue/producers/{$q->id}/products/weekly/{$other->id}", weeklyForm(['name' => 'Other', 'price' => '1.00']))
        ->assertRedirect("/catalogue/producers/{$q->id}/products");
    expect(ProductModel::query()->whereKey($other->id)->value('price_cents'))->toBe(100);
});

it('answers 404 and leaves the product unchanged when the producer in the URL is not its producer (R3)', function (string $method, string $suffix) {
    $a = areaProducer('A');
    $b = areaProducer('B');
    $product = areaProduct($b);
    $url = "/catalogue/producers/{$a->id}/products/weekly/{$product->id}{$suffix}";
    $before = $product->fresh()->toArray();

    $this->actingAs(accountWith(Role::Catalogue))->{$method}($url, $method === 'put' && $suffix === '' ? weeklyForm(['name' => 'Hijacked']) : [])->assertNotFound();

    expect($product->fresh()->toArray())->toBe($before);
})->with([
    'update' => ['put', ''],
    'deactivate' => ['delete', ''],
    'reactivate' => ['put', '/reactivation'],
    'delete unused' => ['delete', '/unused'],
]);

it('answers 404 for an unknown product on the Catàleg mutations and keeps the Producer area\'s domain error (R3)', function () {
    $producer = areaProducer();
    $missing = (string) Str::uuid7();
    $catalogue = accountWith(Role::Catalogue);

    $this->actingAs($catalogue)->delete("/catalogue/producers/{$producer->id}/products/weekly/{$missing}")->assertNotFound();
    $owner = areaProducerAccount($producer);
    $this->actingAs($owner)->put("/producer/products/{$missing}", weeklyForm())->assertSessionHasErrors('domain');
    $this->actingAs($owner)->delete("/producer/products/{$missing}/unused")->assertSessionHasErrors('domain');
});
