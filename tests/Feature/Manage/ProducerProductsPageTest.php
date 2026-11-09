<?php

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function catalogueSupplier(): ProducerModel
{
    return ProducerModel::query()->create(['name' => 'Beekeeper', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
}

function catalogueRecord(ProducerModel $producer, bool $stock = false): ProductModel
{
    return ProductModel::query()->create(['producer_id' => $producer->id, 'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 650, 'variable' => false, 'active' => true, 'stock' => $stock]);
}

it('shows both product modes from the Manager producer record with weekly edit rights (S67, R3)', function () {
    $producer = catalogueSupplier();
    $weekly = catalogueRecord($producer);
    $stock = catalogueRecord($producer, true);

    $this->actingAs(accountWith(Role::Manager))->get("/catalogue/producers/{$producer->id}/products")
        ->assertInertia(fn (Assert $page) => $page->component('Catalogue/Producers/Products')
            ->has('products', 2)
            ->where('products.0.id', $weekly->id)
            ->where('products.0.stock', false)
            ->where('products.0.used', false)
            ->where('products.1.id', $stock->id)
            ->where('products.1.stock', true));
});

it('reclassifies an unused product only as Manager and transfers its administrator (S67)', function () {
    $producer = catalogueSupplier();
    $product = catalogueRecord($producer);
    $path = "/catalogue/products/{$product->id}/classification";
    $this->actingAs(accountWith(Role::Manager))->put($path, ['stock' => true])
        ->assertRedirect("/catalogue/producers/{$producer->id}/products");
    expect($product->refresh()->stock)->toBeTrue();
    $producerAccount = User::factory()->create(['producer_id' => $producer->id]);
    DB::table('account_roles')->insert(['user_id' => $producerAccount->id, 'role' => Role::Producer->value]);
    $this->actingAs($producerAccount)->get("/producer/products/{$product->id}/edit")->assertNotFound();
    $this->actingAs($producerAccount)->put($path, ['stock' => false])->assertForbidden();
    $this->actingAs(accountWith(Role::Manager))->get("/catalogue/producers/{$producer->id}/products/stock/{$product->id}/edit")->assertOk();
    $this->actingAs(accountWith(Role::Manager))->put($path, ['stock' => false])
        ->assertRedirect("/catalogue/producers/{$producer->id}/products");
    expect($product->refresh()->stock)->toBeFalse();
    $this->actingAs($producerAccount)->get("/producer/products/{$product->id}/edit")->assertOk();
    $this->actingAs(accountWith(Role::Manager))->get("/catalogue/producers/{$producer->id}/products/stock/{$product->id}/edit")->assertNotFound();
});

it('keeps Manager catalogue access separate from producer administration including mixed-role accounts (S67, S70)', function () {
    $own = catalogueSupplier();
    $other = catalogueSupplier();
    $ownWeekly = catalogueRecord($own);
    $otherWeekly = catalogueRecord($other);
    $mixed = User::factory()->create(['producer_id' => $own->id]);
    DB::table('account_roles')->insert([
        ['user_id' => $mixed->id, 'role' => Role::Manager->value],
        ['user_id' => $mixed->id, 'role' => Role::Producer->value],
    ]);
    $this->actingAs($mixed)->get("/catalogue/producers/{$other->id}/products")->assertOk();
    $this->actingAs($mixed)->delete("/producer/products/{$otherWeekly->id}/unused")->assertForbidden();
    $this->actingAs($mixed)->delete("/producer/products/{$ownWeekly->id}/unused")->assertRedirect('/producer/products');
    expect(ProductModel::query()->whereKey($ownWeekly->id)->exists())->toBeFalse()
        ->and(ProductModel::query()->whereKey($otherWeekly->id)->exists())->toBeTrue();
});
