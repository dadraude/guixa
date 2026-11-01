<?php

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function discoveryProducer(string $name, bool $active = true): ProducerModel
{
    return ProducerModel::query()->create([
        'name' => $name, 'active' => $active, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
    ]);
}

function discoveryProduct(ProducerModel $producer, string $name, bool $stock = true, bool $active = true): ProductModel
{
    return ProductModel::query()->create([
        'producer_id' => $producer->id, 'name' => $name, 'unit' => 'jar', 'price_cents' => 650,
        'variable' => false, 'active' => $active, 'stock' => $stock,
    ]);
}

function discoveryProducerAccount(ProducerModel $producer, bool $member = false): User
{
    $user = User::factory()->create(['producer_id' => $producer->id]);
    DB::table('account_roles')->insert(['user_id' => $user->id, 'role' => Role::Producer->value]);
    if ($member) {
        linkToNewHousehold($user);
    }

    return $user;
}

it('shows active stock including zero with no cycle or stock writes (S73)', function () {
    $beekeeper = discoveryProducer('Beekeeper', false);
    $oilProducer = discoveryProducer('Oil maker');
    $honey = discoveryProduct($beekeeper, 'Honey');
    discoveryProduct($oilProducer, 'Oil');
    discoveryProduct($beekeeper, 'Jam', active: false);
    discoveryProduct($beekeeper, 'Bread', stock: false);
    DB::table('stock_movements')->insert([
        'id' => (string) Str::uuid7(),
        'stock_product_id' => $honey->id, 'type' => 'entry', 'quantity' => '5.000',
        'occurred_at' => now(), 'recorded_by' => accountWith(Role::Manager)->id,
    ]);
    $before = DB::table('stock_movements')->count();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs(accountWith(Role::Member))->get('/stock')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Stock/Index')
            ->has('products', 2)
            ->where('products.0.name', 'Honey')
            ->where('products.0.producerName', 'Beekeeper')
            ->where('products.0.price', '6.50')
            ->where('products.0.available', '5.000')
            ->where('products.1.name', 'Oil')
            ->where('products.1.soldOut', true));

    $stockReads = array_filter(DB::getQueryLog(), fn (array $query) => str_starts_with(strtolower($query['query']), 'select')
        && str_contains($query['query'], 'stock_movements'));
    DB::disableQueryLog();
    expect($stockReads)->toHaveCount(1);
    expect(DB::table('stock_movements')->count())->toBe($before);
});

it('keeps producer stock private while a linked member can use the cooperative catalogue (S74)', function () {
    $beekeeper = discoveryProducer('Beekeeper');
    $oilProducer = discoveryProducer('Oil maker');
    discoveryProduct($beekeeper, 'Honey');
    discoveryProduct($beekeeper, 'Old jam', active: false);
    discoveryProduct($oilProducer, 'Oil');
    discoveryProduct($beekeeper, 'Bread', stock: false);
    $account = discoveryProducerAccount($beekeeper, true);

    $this->actingAs($account)->get('/producer/products')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->has('stockProducts', 2)
            ->where('stockProducts.0.name', 'Honey')
            ->where('stockProducts.1.name', 'Old jam')
            ->where('stockProducts.1.active', false));
    $this->actingAs($account)->get('/stock')
        ->assertInertia(fn (Assert $page) => $page->has('products', 2));

    $producerOnly = discoveryProducerAccount($oilProducer);
    $this->actingAs($producerOnly)->get('/stock')->assertForbidden();
    $this->actingAs($producerOnly)->get('/producer/products')
        ->assertInertia(fn (Assert $page) => $page->has('stockProducts', 1)->where('stockProducts.0.name', 'Oil'));
});

it('rejects stock mutation attempts from a producer even with a forged stock id (R25b)', function () {
    $producer = discoveryProducer('Beekeeper');
    $stock = discoveryProduct($producer, 'Honey');
    $account = discoveryProducerAccount($producer);

    $this->actingAs($account)->put("/producer/products/{$stock->id}", [
        'name' => 'Changed', 'unit' => 'jar', 'price' => '1.00', 'variable' => false,
    ])->assertForbidden();
    $this->actingAs($account)->delete("/producer/products/{$stock->id}")->assertForbidden();
    $this->actingAs($account)->post("/manage/stock/{$stock->id}/entries", ['quantity' => '1'])->assertForbidden();
    expect($stock->fresh()->name)->toBe('Honey');
});
