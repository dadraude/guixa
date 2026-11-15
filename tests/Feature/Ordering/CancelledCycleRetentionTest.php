<?php

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Application\AddStockToBasket;
use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Sales\Application\ValidateBasket;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\RecordStockEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @return array<string, list<array<string, mixed>>> */
function ledgerRows(): array
{
    return [
        'baskets' => DB::table('baskets')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        'basket_lines' => DB::table('basket_lines')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        'wallet' => DB::table('wallet_movements')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        'stock' => DB::table('stock_movements')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
    ];
}

it('cancels a cycle with weekly orders, retaining them as history, charging nothing and leaving independent stock baskets unchanged (R4b, R19b, S37, S87)', function () {
    [$cycleId, $baker, $loaf] = cycleWithBaker();
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household H', 'created_at' => now(), 'updated_at' => now()]);
    $anna = memberOf($household, 'Anna');
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '3'])->assertRedirect();

    $manager = accountWith(Role::Manager);
    $actor = Actor::manager($manager->id);
    $producer = ProducerModel::query()->create(['name' => 'Oil maker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $oil = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $oil, 'producer_id' => $producer->id, 'stock' => true, 'name' => 'Oil', 'unit' => 'bottle', 'price_cents' => 1200, 'variable' => false, 'active' => true]);
    app(RecordStockEntry::class)($actor, $oil, '4', null);
    $stockBasket = app(CreateStockBasket::class)($actor, $household);
    app(AddStockToBasket::class)($actor, $stockBasket, $oil, '1');
    app(ValidateBasket::class)($actor, $stockBasket);
    $before = ledgerRows();
    $orderId = DB::table('orders')->where('cycle_id', $cycleId)->value('id');

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/cancel")->assertRedirect()->assertSessionHasNoErrors();

    expect(DB::table('cycles')->where('id', $cycleId)->value('cancelled_at'))->not->toBeNull()
        ->and(DB::table('orders')->where('id', $orderId)->value('cancelled_at'))->not->toBeNull()
        ->and(DB::table('order_lines')->where('order_id', $orderId)->count())->toBe(1)
        ->and(DB::table('order_lines')->where('order_id', $orderId)->value('ordered_quantity'))->toEqual('3.000')
        ->and(DB::table('baskets')->where('kind', 'weekly')->count())->toBe(0)
        ->and(ledgerRows())->toEqual($before);

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}")->assertOk()->assertInertia(fn ($page) => $page
        ->where('summary.cancelled', true)->has('summary.producers', 0));
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '1'])->assertSessionHasErrors('domain');

    // The cancelled order is retained history (R19b): the product counts as used, so its producer cannot delete it (S87).
    $producerAccount = User::factory()->create();
    DB::table('account_roles')->insert(['user_id' => $producerAccount->id, 'role' => Role::Producer->value]);
    DB::table('users')->where('id', $producerAccount->id)->update(['producer_id' => $baker->id]);
    $this->actingAs($producerAccount->fresh())->delete("/producer/products/{$loaf}/unused")->assertSessionHasErrors('domain');
    expect(DB::table('products')->where('id', $loaf)->exists())->toBeTrue();
});
