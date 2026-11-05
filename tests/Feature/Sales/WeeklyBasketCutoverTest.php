<?php

use App\Models\User;
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
    $reception = "/manage/cycles/{$cycleId}/receptions/{$baker->id}";
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
