<?php

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Ordering\Domain\Error\CycleNotSettled;
use Guixa\Sales\Application\WeeklyBasketSource;
use Guixa\Shared\Domain\Role;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A sent baker order: household A ordered 5 loaves (€3.50) and the reception allocated it 4; household B ordered nothing.
 *
 * @return array{string, string, string, string, string}
 */
function settledWeeklyFixture(): array
{
    [$cycleId, $baker, $loaf] = cycleWithBaker();
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household A', 'created_at' => now(), 'updated_at' => now()]);
    test()->actingAs(memberOf($household, 'Anna'))->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '5'])->assertRedirect();
    app('auth')->forgetGuards();
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['closes_at' => now()->subHour(), 'sent_at' => now()]);

    $manager = accountWith(Role::Manager);
    $reception = "/receptions/{$cycleId}/{$baker->id}";
    test()->actingAs($manager)->put("{$reception}/products/{$loaf}/received", ['received' => '4'])->assertRedirect();
    test()->post("{$reception}/validate")->assertRedirect();
    test()->post("{$reception}/distribution")->assertRedirect();

    return [$cycleId, $household, $loaf, $manager->id, $baker->id];
}

it('settles into one uncharged weekly base basket that validates once, closes the cycle and collects without a second charge (R18, R22, R24, S9, S27)', function () {
    [$cycleId, $household, $loaf] = settledWeeklyFixture();

    $order = DB::table('orders')->where('cycle_id', $cycleId)->where('household_id', $household)->first();
    $basket = DB::table('baskets')->where('source_order_id', $order->id)->first();
    expect($basket)->not->toBeNull()
        ->and($basket->kind)->toBe('weekly')
        ->and($basket->state)->toBe('draft')
        ->and($order->cycle_id)->toBe($cycleId)
        ->and($basket->validated_total_cents)->toBeNull();
    $line = DB::table('basket_lines')->where('basket_id', $basket->id)->first();
    expect($line->kind)->toBe('weekly')
        ->and($line->product_id)->toBe($loaf)
        ->and($line->source_order_line_id)->toBe(DB::table('order_lines')->where('order_id', $order->id)->value('id'))
        ->and((float) $line->ordered_quantity)->toBe(5.0)
        ->and((float) $line->allocated_quantity)->toBe(4.0)
        ->and((float) $line->quantity)->toBe(4.0)
        ->and(DB::table('wallet_movements')->count())->toBe(0)
        ->and(DB::table('cycles')->where('id', $cycleId)->value('closed_at'))->toBeNull();

    expect(fn () => DB::transaction(fn () => DB::table('baskets')->insert([
        'id' => (string) Str::uuid7(), 'household_id' => $household, 'household_name' => 'Household A', 'kind' => 'weekly', 'state' => 'draft',
        'created_by' => null, 'created_at' => now(), 'source_order_id' => $order->id,
    ])))->toThrow(QueryException::class);

    $finance = accountWith(Role::Finance);
    $this->actingAs($finance)->post("/finance/baskets/{$basket->id}/validation")->assertRedirect();
    $this->post("/finance/baskets/{$basket->id}/validation")->assertSessionHasErrors('domain');

    expect(DB::table('baskets')->where('id', $basket->id)->value('validated_total_cents'))->toBe(1400)
        ->and(DB::table('wallet_movements')->where('basket_id', $basket->id)->pluck('amount_cents')->all())->toBe([-1400])
        ->and(DB::table('cycles')->where('id', $cycleId)->value('closed_at'))->not->toBeNull();

    $this->actingAs(accountWith(Role::Manager))->put("/finance/baskets/{$basket->id}/collection")->assertRedirect();
    expect(DB::table('baskets')->where('id', $basket->id)->value('state'))->toBe('collected')
        ->and(DB::table('wallet_movements')->count())->toBe(1);
});

it('refuses the retired charge-on-collection and abandonment endpoints so an old client replay charges nothing (R22, S9)', function () {
    [$cycleId, $household, , $managerId] = settledWeeklyFixture();
    $manager = User::query()->find($managerId);

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$household}/complete")->assertStatus(404);
    $this->post("/manage/cycles/{$cycleId}/pickups/{$household}/not-collected")->assertStatus(404);

    expect(DB::table('wallet_movements')->count())->toBe(0)
        ->and(DB::table('cycles')->where('id', $cycleId)->value('closed_at'))->toBeNull();
});

it('reports the weekly source not ready until the last producer is distributed, while a stock-only basket validates without the cycle settling (R18, R19, S27)', function () {
    [$cycleId, $baker, $loaf] = cycleWithBaker();
    $grower = ProducerModel::query()->create(['name' => 'Grower', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $apples = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $apples, 'producer_id' => $grower->id, 'name' => 'Apples', 'unit' => 'bag', 'price_cents' => 280, 'variable' => false, 'active' => true]);
    DB::table('cycle_producers')->insert(['cycle_id' => $cycleId, 'producer_id' => $grower->id, 'participating' => true, 'closes_at' => null]);
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household H', 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs(memberOf($household, 'Hana'))->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2'])->assertRedirect();
    $this->put("/cycles/{$cycleId}/order/lines/{$apples}", ['quantity' => '1'])->assertRedirect();
    app('auth')->forgetGuards();
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['closes_at' => now()->subHour(), 'sent_at' => now()]);
    $manager = accountWith(Role::Manager);
    $distribute = function (string $producerId, string $product, string $received) use ($cycleId, $manager) {
        $reception = "/receptions/{$cycleId}/{$producerId}";
        $this->actingAs($manager)->put("{$reception}/products/{$product}/received", ['received' => $received])->assertRedirect();
        $this->post("{$reception}/validate")->assertRedirect();
        $this->post("{$reception}/distribution")->assertRedirect();
    };
    $source = fn () => app(WeeklyBasketSource::class)->lockSettled($cycleId);

    $distribute($baker->id, $loaf, '2');

    expect(fn () => DB::transaction($source))->toThrow(CycleNotSettled::class)
        ->and(DB::table('cycles')->where('id', $cycleId)->value('settled_at'))->toBeNull()
        ->and(DB::table('baskets')->where('kind', 'weekly')->count())->toBe(0);

    $beekeeper = ProducerModel::query()->create(['name' => 'Beekeeper', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $honey = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $honey, 'producer_id' => $beekeeper->id, 'stock' => true, 'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 600, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'product_id' => $honey, 'type' => 'entry', 'quantity' => '3', 'occurred_at' => now(), 'recorded_by' => $manager->id]);
    $finance = accountWith(Role::Finance);
    $this->actingAs($finance);
    $this->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $stockBasket = (string) DB::table('baskets')->where('household_id', $household)->where('kind', 'stock')->value('id');
    $this->post("/finance/baskets/{$stockBasket}/stock-lines", ['stock_product_id' => $honey, 'stock_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$stockBasket}/validation")->assertRedirect();

    expect(DB::table('wallet_movements')->where('basket_id', $stockBasket)->pluck('amount_cents')->all())->toBe([-600])
        ->and(DB::table('cycles')->where('id', $cycleId)->value('settled_at'))->toBeNull()
        ->and(fn () => DB::transaction($source))->toThrow(CycleNotSettled::class);

    $distribute($grower->id, $apples, '1');

    DB::transaction($source);
    expect(DB::table('cycles')->where('id', $cycleId)->value('settled_at'))->not->toBeNull()
        ->and(DB::table('baskets')->where('kind', 'weekly')->where('household_id', $household)->value('state'))->toBe('draft');
});
