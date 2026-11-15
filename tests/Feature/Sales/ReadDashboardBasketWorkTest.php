<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Application\DashboardBasketScope;
use Guixa\Sales\Application\DashboardBasketStage;
use Guixa\Sales\Application\ReadDashboardBasketWork;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('reads pending collections with the cycle pickup date, for one household or all, without an actor (R4, R8, R14)', function () {
    $week = lateArrivalWeek();
    $this->post("/finance/baskets/{$week['baskets']['B']}/validation")->assertRedirect();
    $pickupDate = (string) DB::table('cycles')->where('id', $week['cycle'])->value('pickup_date');

    $all = app(ReadDashboardBasketWork::class)();
    $mine = app(ReadDashboardBasketWork::class)($week['households']['A']);

    expect(collect($all)->pluck('basketId')->sort()->values()->all())->toBe(collect([$week['baskets']['A'], $week['baskets']['B']])->sort()->values()->all())
        ->and(collect($all)->pluck('cycleId')->unique()->all())->toBe([$week['cycle']])
        ->and(collect($all)->pluck('pickupDate')->unique()->all())->toBe([$pickupDate])
        ->and($mine)->toHaveCount(1)
        ->and($mine[0]->basketId)->toBe($week['baskets']['A']);
});

it('reads draft baskets that validation accepts, with their cycle date, and none of the validated, cancelled or emptied (R7, S7)', function () {
    $week = lateArrivalWeek();
    $pickupDate = (string) DB::table('cycles')->where('id', $week['cycle'])->value('pickup_date');
    DB::table('basket_lines')->where('basket_id', $week['baskets']['K'])->update(['removed_at' => now()]);
    // A cancelled independent draft keeps its lines but is no longer work.
    $producer = ProducerModel::query()->create(['name' => 'Maker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $product = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $product, 'producer_id' => $producer->id, 'stock' => true,
        'name' => 'Oil', 'unit' => 'bottle', 'price_cents' => 1200, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'product_id' => $product,
        'type' => 'entry', 'quantity' => '10', 'occurred_at' => now(), 'recorded_by' => $week['manager']->id]);
    $this->actingAs($week['manager'])->post('/finance/baskets', ['household_id' => $week['households']['A']])->assertRedirect();
    $cancelled = (string) DB::table('baskets')->where('kind', 'stock')->latest('id')->value('id');
    $this->post("/finance/baskets/{$cancelled}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => '2'])->assertRedirect();
    $this->post("/finance/baskets/{$cancelled}/cancellation")->assertRedirect();
    expect(DB::table('baskets')->where('id', $cancelled)->value('state'))->toBe('cancelled');

    $drafts = app(ReadDashboardBasketWork::class)(null, DashboardBasketScope::Drafts);

    expect(collect($drafts)->pluck('basketId')->all())->toBe([$week['baskets']['B']])
        ->and($drafts[0]->stage)->toBe(DashboardBasketStage::Validate)
        ->and($drafts[0]->cycleId)->toBe($week['cycle'])
        ->and($drafts[0]->pickupDate)->toBe($pickupDate);
});
