<?php

use Carbon\CarbonImmutable;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\FrozenClock;

/**
 * A second cycle, a week after the settled one in {@see twoHouseholdWeek()}, with a greengrocer that stays open.
 *
 * @return array{string, string}
 */
function nextCycleWithApples(): array
{
    $grocer = ProducerModel::query()->create(['name' => 'Greengrocer', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $apples = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $apples, 'producer_id' => $grocer->id, 'name' => 'Apples', 'unit' => 'kg', 'price_cents' => 280, 'variable' => true, 'active' => true]);
    $cycle = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $cycle, 'pickup_date' => now()->addDays(37)->toDateString()]);
    DB::table('cycle_producers')->insert(['cycle_id' => $cycle, 'producer_id' => $grocer->id, 'participating' => true, 'closes_at' => null]);

    return [$cycle, $apples];
}

it('charges only the validated basket and leaves the next cycle editable while it waits for collection (R4, R22, R23b, S9, S26)', function () {
    $week = twoHouseholdWeek();
    [$next, $apples] = nextCycleWithApples();
    $anna = memberOf($week['households']['A'], 'Anna');
    $this->actingAs($anna)->put("/cycles/{$next}/order/lines/{$apples}", ['quantity' => '2'])->assertRedirect();

    $this->actingAs($week['manager'])->post("/finance/baskets/{$week['baskets']['A']}/validation")->assertRedirect();
    $charge = DB::table('wallet_movements')->where('household_id', $week['households']['A'])->get();
    expect($charge)->toHaveCount(1)
        ->and($charge[0]->basket_id)->toBe($week['baskets']['A'])
        ->and($charge[0]->amount_cents)->toBe(-1400);

    $afterPickup = CarbonImmutable::parse((string) DB::table('cycles')->where('id', $week['cycle'])->value('pickup_date'))->addDay()->setTime(10, 0);
    $this->app->instance(Clock::class, new FrozenClock($afterPickup->toIso8601String(), config('guixa.timezone')));
    $this->actingAs($anna)->put("/baskets/{$week['baskets']['A']}/collection")->assertRedirect()->assertSessionHasNoErrors();
    $this->put("/cycles/{$next}/order/lines/{$apples}", ['quantity' => '3.500'])->assertRedirect()->assertSessionHasNoErrors();

    expect(substr((string) DB::table('baskets')->where('id', $week['baskets']['A'])->value('collected_at'), 0, 10))
        ->toBeGreaterThan((string) DB::table('cycles')->where('id', $week['cycle'])->value('pickup_date'))
        ->and(DB::table('baskets')->where('id', $week['baskets']['A'])->value('state'))->toBe('collected')
        ->and(DB::table('wallet_movements')->where('household_id', $week['households']['A'])->pluck('amount_cents')->all())->toBe([-1400])
        ->and(DB::table('order_lines')->where('product_id', $apples)->value('ordered_quantity'))->toEqual('3.500')
        ->and(DB::table('baskets')->where('household_id', $week['households']['A'])->count())->toBe(1)
        ->and(DB::table('cycles')->where('id', $next)->value('closed_at'))->toBeNull()
        ->and(DB::table('stock_movements')->count())->toBe(0);
});

it('opens a cycle for a manager while earlier baskets are pending collection (R4)', function () {
    $week = twoHouseholdWeek();
    $this->actingAs($week['manager'])->post("/finance/baskets/{$week['baskets']['A']}/validation")->assertRedirect();
    $grocer = ProducerModel::query()->create(['name' => 'Greengrocer', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    DB::table('cooperative_settings')->upsert([['id' => 1, 'pickup_weekday' => now()->addDays(44)->dayOfWeekIso]], ['id'], ['pickup_weekday']);

    $pickup = now()->addDays(44)->toDateString();
    $this->post('/manage/cycles', ['pickup_date' => $pickup, 'producer_ids' => [$grocer->id]])->assertSessionHasNoErrors();

    expect(DB::table('cycles')->where('pickup_date', $pickup)->exists())->toBeTrue()
        ->and(DB::table('baskets')->where('id', $week['baskets']['A'])->value('state'))->toBe('validated')
        ->and(DB::table('cycles')->where('id', $week['cycle'])->value('cancelled_at'))->toBeNull();
});
