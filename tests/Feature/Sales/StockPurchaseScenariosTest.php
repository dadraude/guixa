<?php

use Carbon\CarbonImmutable;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Role;
use Guixa\Shared\Domain\UtcTimestamp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FrozenClock;

/**
 * An active household and an active stock product with its available quantity.
 *
 * @return array{string, string, string}
 */
function stockScenario(int $priceCents, string $available, bool $producerActive = true): array
{
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household H', 'created_at' => now(), 'updated_at' => now()]);
    $producer = ProducerModel::query()->create(['name' => 'Beekeeper', 'active' => $producerActive, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $product = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $product, 'producer_id' => $producer->id, 'stock' => true, 'name' => 'Honey', 'unit' => 'jar', 'price_cents' => $priceCents, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'product_id' => $product, 'type' => 'entry', 'quantity' => $available, 'occurred_at' => UtcTimestamp::toStorage(now()), 'recorded_by' => accountWith(Role::Manager)->id]);

    return [$household, $product, $producer->id];
}

function stockBasketOf(string $household, string $product, string $quantity): string
{
    test()->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $basket = (string) DB::table('baskets')->where('household_id', $household)->orderByDesc('created_at')->orderByDesc('id')->value('id');
    test()->post("/finance/baskets/{$basket}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => $quantity])->assertRedirect();

    return $basket;
}

it('keeps a confirmed receipt separate from the basket charge and leaves collection without a second movement (R21, R22, R29, S19)', function () {
    [$household, $product] = stockScenario(3000, '2');
    $finance = accountWith(Role::Finance);
    $this->actingAs($finance)->post("/finance/households/{$household}/wallet/top-ups", ['amount' => '5'])->assertRedirect();
    $basket = stockBasketOf($household, $product, '1');

    $this->get("/finance/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.totalCents', 3000)->where('basket.balanceCents', 500)->where('basket.projectedBalanceCents', -2500));
    $this->post("/finance/households/{$household}/wallet/top-ups", ['amount' => '40', 'note' => 'cash'])->assertRedirect();
    $this->post("/finance/baskets/{$basket}/validation")->assertRedirect();

    $movements = DB::table('wallet_movements')->where('household_id', $household)->orderBy('amount_cents')->get();
    expect($movements->pluck('amount_cents')->all())->toBe([-3000, 500, 4000])
        ->and($movements->where('amount_cents', 4000)->first()->basket_id)->toBeNull()
        ->and($movements->where('amount_cents', -3000)->first()->basket_id)->toBe($basket)
        ->and($movements->where('amount_cents', 4000)->first()->type)->toBe('top_up');
    $this->get("/finance/households/{$household}/wallet")->assertInertia(fn (Assert $page) => $page->where('wallet.balance', '15.00'));

    $this->put("/finance/baskets/{$basket}/collection")->assertRedirect();
    expect(DB::table('wallet_movements')->where('household_id', $household)->count())->toBe(3)
        ->and(DB::table('stock_movements')->where('basket_id', $basket)->count())->toBe(1);
});

it('sells remaining stock of an inactive producer through a cycleless basket and keeps it in the member catalogue (R3a, R19, R20, R22, R25c, S75)', function () {
    [$household, $product] = stockScenario(600, '5', producerActive: false);
    $member = memberOf($household);
    $this->actingAs($member)->get('/stock')->assertInertia(fn (Assert $page) => $page
        ->has('products', 1)->where('products.0.producerName', 'Beekeeper')->where('products.0.available', '5.000'));
    expect(DB::table('cycles')->count())->toBe(0);

    $this->actingAs(accountWith(Role::Finance));
    $basket = stockBasketOf($household, $product, '2');
    $this->post("/finance/baskets/{$basket}/validation")->assertRedirect();

    expect(DB::table('wallet_movements')->where('household_id', $household)->pluck('amount_cents')->all())->toBe([-1200])
        ->and(DB::table('stock_movements')->where('basket_id', $basket)->pluck('quantity')->all())->toEqual(['-2.000']);
    $this->actingAs($member)->get('/stock')->assertInertia(fn (Assert $page) => $page
        ->where('products.0.available', '3.000')->where('products.0.soldOut', false));
});

it('records a later supplementary basket as its own sale and charge without touching the closed weekly basket (R19, R22, R23b, R24b, S80)', function () {
    $week = twoHouseholdWeek();
    $finance = accountWith(Role::Finance);
    $this->actingAs($week['manager'])->post("/finance/baskets/{$week['baskets']['A']}/validation")->assertRedirect();
    $this->post("/finance/baskets/{$week['baskets']['B']}/validation")->assertRedirect();
    $household = $week['households']['A'];
    $closedAt = DB::table('cycles')->where('id', $week['cycle'])->value('closed_at');
    expect($closedAt)->not->toBeNull();
    $pickup = DB::table('cycles')->where('id', $week['cycle'])->value('pickup_date');
    $weeklyRows = DB::table('baskets')->where('id', $week['baskets']['A'])->first();
    $weeklyCharge = DB::table('wallet_movements')->where('basket_id', $week['baskets']['A'])->first();

    $this->app->instance(Clock::class, new FrozenClock(CarbonImmutable::parse((string) $pickup)->addDay()->setTime(10, 0)->toIso8601String(), config('guixa.timezone')));
    $this->actingAs(memberOf($household))
        ->put("/baskets/{$week['baskets']['A']}/collection")->assertRedirect();

    $producer = ProducerModel::query()->create(['name' => 'Beekeeper', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $honey = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $honey, 'producer_id' => $producer->id, 'stock' => true, 'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 700, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'product_id' => $honey, 'type' => 'entry', 'quantity' => '3', 'occurred_at' => UtcTimestamp::toStorage(now()), 'recorded_by' => $finance->id]);
    $this->actingAs($finance);
    $late = stockBasketOf($household, $honey, '1');
    $this->post("/finance/baskets/{$late}/validation")->assertRedirect();
    $this->put("/finance/baskets/{$late}/collection")->assertRedirect();

    $lateRow = DB::table('baskets')->where('id', $late)->first();
    expect($lateRow->state)->toBe('collected')
        ->and($lateRow->source_order_id)->toBeNull()
        ->and(substr((string) $lateRow->validated_at, 0, 10))->toBe(CarbonImmutable::parse($pickup)->addDay()->toDateString())
        ->and(DB::table('wallet_movements')->where('basket_id', $late)->pluck('amount_cents')->all())->toBe([-700])
        ->and(DB::table('stock_movements')->where('basket_id', $late)->pluck('quantity')->all())->toEqual(['-1.000'])
        ->and(DB::table('wallet_movements')->where('basket_id', $week['baskets']['A'])->first())->toEqual($weeklyCharge)
        ->and(DB::table('baskets')->where('id', $week['baskets']['A'])->value('validated_total_cents'))->toBe($weeklyRows->validated_total_cents)
        ->and(DB::table('cycles')->where('id', $week['cycle'])->value('closed_at'))->toBe($closedAt)
        ->and(DB::table('wallet_movements')->where('household_id', $household)->count())->toBe(2);
});

it('rejects a weekly product as a basket stock line while the stock variant of the same producer is sold normally (R20, R26, S76)', function () {
    [$household, $stockHoney, $producer] = stockScenario(1200, '2');
    $weeklyHoney = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $weeklyHoney, 'producer_id' => $producer, 'stock' => false, 'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 1200, 'variable' => false, 'active' => true]);
    $this->actingAs(accountWith(Role::Finance))->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $basket = (string) DB::table('baskets')->where('household_id', $household)->value('id');

    $this->post("/finance/baskets/{$basket}/stock-lines", ['stock_product_id' => $weeklyHoney, 'stock_quantity' => '1'])->assertSessionHasErrors('domain');
    expect(DB::table('basket_lines')->where('basket_id', $basket)->count())->toBe(0);

    $this->post("/finance/baskets/{$basket}/stock-lines", ['stock_product_id' => $stockHoney, 'stock_quantity' => '1'])->assertSessionHasNoErrors();
    expect(DB::table('basket_lines')->where('basket_id', $basket)->pluck('product_id')->all())->toBe([$stockHoney]);
});
