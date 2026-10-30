<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function basketHousehold(): string
{
    $id = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $id, 'name' => 'Household K', 'created_at' => now(), 'updated_at' => now()]);

    return $id;
}

it('shows the independent basket list and create form only to a Manager (T55, R19a)', function () {
    $household = basketHousehold();
    $this->get('/finance/baskets')->assertRedirect(route('login'));
    $this->actingAs(memberOf($household))->get('/finance/baskets')->assertForbidden();
    $this->actingAs(accountWith(Role::Producer))->get('/finance/baskets/create')->assertForbidden();

    $this->actingAs(accountWith(Role::Manager))->get('/finance/baskets')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Finance/Baskets/Index')->has('baskets', 0));
    $this->get('/finance/baskets/create')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Finance/Baskets/Create')->where('households.0.id', $household));
});

it('creates, adds, validates and collects through Manager routes while denying member writes (S18, S79, S22)', function () {
    $household = basketHousehold();
    $manager = accountWith(Role::Manager);
    $producer = ProducerModel::query()->create([
        'name' => 'Oil maker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
    ]);
    $product = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $product, 'producer_id' => $producer->id, 'stock' => true,
        'name' => 'Oil', 'unit' => 'bottle', 'price_cents' => 1200, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'stock_product_id' => $product,
        'type' => 'entry', 'quantity' => '2', 'occurred_at' => now(), 'recorded_by' => $manager->id]);

    $this->actingAs($manager)->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $id = DB::table('baskets')->value('id');
    $this->get('/finance/baskets')->assertInertia(fn (Assert $page) => $page
        ->where('baskets.0.createdAt', fn (string $value) => preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1));
    $this->get("/finance/baskets/{$id}")->assertInertia(fn (Assert $page) => $page
        ->component('Finance/Baskets/Show')->where('basket.state', 'draft')
        ->where('basket.balanceCents', 0)->has('basket.stockLines', 0));

    $this->actingAs(memberOf($household))->post("/finance/baskets/{$id}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => '1'])->assertForbidden();
    $this->post("/finance/baskets/{$id}/validation")->assertForbidden();
    $this->put("/finance/baskets/{$id}/collection")->assertForbidden();
    $this->actingAs($manager)->post("/finance/baskets/{$id}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => '1'])->assertRedirect();
    $this->get("/finance/baskets/{$id}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.stockLines.0.name', 'Oil')->where('basket.totalCents', 1200)
        ->where('basket.projectedBalanceCents', -1200));

    $this->post("/finance/baskets/{$id}/validation")->assertRedirect();
    $this->get("/finance/baskets/{$id}")->assertInertia(fn (Assert $page) => $page->where('basket.state', 'validated'));
    $this->put("/finance/baskets/{$id}/collection")->assertRedirect();
    $this->get("/finance/baskets/{$id}")->assertInertia(fn (Assert $page) => $page->where('basket.state', 'collected'));
    expect(DB::table('stock_movements')->where('basket_id', $id)->count())->toBe(1)
        ->and(DB::table('wallet_movements')->where('basket_id', $id)->count())->toBe(1);
});

it('lets a Manager remove stock and cancel a draft through HTTP while retaining its reference (R19b, S87)', function () {
    $household = basketHousehold();
    $manager = accountWith(Role::Manager);
    $producer = ProducerModel::query()->create([
        'name' => 'Oil maker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
    ]);
    $product = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $product, 'producer_id' => $producer->id, 'stock' => true,
        'name' => 'Oil', 'unit' => 'bottle', 'price_cents' => 1200, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'stock_product_id' => $product,
        'type' => 'entry', 'quantity' => '2', 'occurred_at' => now(), 'recorded_by' => $manager->id]);

    $this->actingAs($manager)->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $id = DB::table('baskets')->value('id');
    $this->post("/finance/baskets/{$id}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => '1'])->assertRedirect();
    $this->delete("/finance/baskets/{$id}/stock-lines/{$product}")->assertRedirect();
    $this->get("/finance/baskets/{$id}")->assertInertia(fn (Assert $page) => $page->has('basket.stockLines', 0));
    $this->post("/finance/baskets/{$id}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$id}/cancellation")->assertRedirect();
    $this->get("/finance/baskets/{$id}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.state', 'cancelled')->has('basket.stockLines', 0)->where('basket.totalCents', 0));
    $this->get('/finance/baskets')->assertInertia(fn (Assert $page) => $page
        ->where('baskets.0.state', 'cancelled')->where('baskets.0.totalCents', 0));

    expect(DB::table('basket_lines')->where('basket_id', $id)->where('product_id', $product)->whereNotNull('removed_at')->count())->toBe(1)
        ->and(DB::table('basket_lines')->where('basket_id', $id)->count())->toBe(2)
        ->and(DB::table('stock_movements')->where('basket_id', $id)->count())->toBe(0)
        ->and(DB::table('wallet_movements')->where('basket_id', $id)->count())->toBe(0);
});

it('denies every basket read and mutation route to non-managers at T55 (R19a)', function (string $method, string $uri) {
    $household = basketHousehold();
    $basket = (string) Str::uuid7();
    $product = (string) Str::uuid7();
    $uri = str_replace(['{basket}', '{product}'], [$basket, $product], $uri);
    $payload = ['household_id' => $household, 'stock_product_id' => $product, 'stock_quantity' => '1'];

    $this->actingAs(memberOf($household))->{$method}($uri, $payload)->assertForbidden();
    $this->actingAs(accountWith(Role::Producer))->{$method}($uri, $payload)->assertForbidden();
})->with([
    ['get', '/finance/baskets'],
    ['get', '/finance/baskets/create'],
    ['post', '/finance/baskets'],
    ['get', '/finance/baskets/{basket}'],
    ['post', '/finance/baskets/{basket}/stock-lines'],
    ['delete', '/finance/baskets/{basket}/stock-lines/{product}'],
    ['post', '/finance/baskets/{basket}/validation'],
    ['put', '/finance/baskets/{basket}/collection'],
    ['post', '/finance/baskets/{basket}/cancellation'],
]);

it('shows a basket-sourced charge on Manager and member Wallet statements without member basket access (R31)', function () {
    $household = basketHousehold();
    $manager = accountWith(Role::Manager);
    $basket = app(CreateStockBasket::class)(Actor::manager($manager->id), $household);
    DB::table('wallet_movements')->insert(['id' => (string) Str::uuid7(), 'household_id' => $household,
        'type' => 'charge', 'amount_cents' => -1200, 'occurred_at' => now(),
        'recorded_by' => $manager->id, 'basket_id' => $basket]);

    $this->actingAs($manager)->get("/manage/households/{$household}/wallet")
        ->assertInertia(fn (Assert $page) => $page->where('wallet.movements.0.basketId', $basket));
    $this->actingAs(memberOf($household))->get('/wallet')
        ->assertInertia(fn (Assert $page) => $page->where('wallet.movements.0.basketId', $basket));
    $this->get("/finance/baskets/{$basket}")->assertForbidden();
});
