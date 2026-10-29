<?php

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** A baker with an account, one product and a cycle in a month. */
function availabilityFixture(string $bakerName = 'Baker', bool $withCycle = true): array
{
    $baker = ProducerModel::query()->create(['name' => $bakerName, 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $account = User::factory()->create(['producer_id' => $baker->id]);
    DB::table('account_roles')->insert(['user_id' => $account->id, 'role' => Role::Producer->value]);
    $jam = (string) Str::uuid7();
    DB::table('products')->insert([
        'id' => $jam, 'producer_id' => $baker->id, 'name' => 'Jam', 'unit' => 'jar', 'price_cents' => 450, 'variable' => false, 'active' => true,
    ]);
    $cycleId = (string) Str::uuid7();

    if ($withCycle) {
        DB::table('cycles')->insert(['id' => $cycleId, 'pickup_date' => now()->addDays(30)->toDateString()]);
    }

    return [$account, $cycleId, $jam];
}

it('keeps the availability route to producers (R3c)', function () {
    [, $cycleId, $jam] = availabilityFixture();
    $uri = "/producer/cycles/{$cycleId}/products/{$jam}/availability";

    $this->put($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Manager))->put($uri)->assertForbidden();
});

it('marks an own product unavailable in a cycle and available again (R3c, S48)', function () {
    [$account, $cycleId, $jam] = availabilityFixture();
    $uri = "/producer/cycles/{$cycleId}/products/{$jam}/availability";

    $this->actingAs($account)->put($uri, ['available' => false])->assertRedirect();
    expect(DB::table('product_unavailabilities')->where('cycle_id', $cycleId)->pluck('product_id')->all())->toBe([$jam]);

    $this->actingAs($account)->put($uri, ['available' => true])->assertRedirect();
    expect(DB::table('product_unavailabilities')->count())->toBe(0);
});

it('rejects a stock Catalog ID of the same producer for cycle availability (R3c, S66)', function () {
    [$account, $cycleId, $weeklyId] = availabilityFixture();
    $producerId = DB::table('products')->where('id', $weeklyId)->value('producer_id');
    $stock = ProductModel::query()->create([
        'producer_id' => $producerId, 'stock' => true, 'name' => 'Stock jam', 'unit' => 'jar',
        'price_cents' => 450, 'variable' => false, 'active' => true,
    ]);

    $this->actingAs($account)->put("/producer/cycles/{$cycleId}/products/{$stock->id}/availability", ['available' => false])
        ->assertSessionHasErrors('domain');
    expect(DB::table('product_unavailabilities')->count())->toBe(0);

    $this->actingAs($account)->put("/producer/cycles/{$cycleId}/products/{$weeklyId}/availability", ['available' => false])->assertRedirect();
    expect(DB::table('product_unavailabilities')->pluck('product_id')->all())->toBe([$weeklyId]);
});

it('validates that availability is given (R3c)', function () {
    [$account, $cycleId, $jam] = availabilityFixture();

    $this->actingAs($account)->put("/producer/cycles/{$cycleId}/products/{$jam}/availability", [])->assertSessionHasErrors('available');
});

it('refuses another producer\'s product and an unknown cycle (R2, R3c)', function () {
    [$account, $cycleId] = availabilityFixture();
    [, , $cheese] = availabilityFixture('Cheesemaker', withCycle: false);

    $this->actingAs($account)->put("/producer/cycles/{$cycleId}/products/{$cheese}/availability", ['available' => false])->assertForbidden();
    expect(DB::table('product_unavailabilities')->count())->toBe(0);

    [$account, , $jam] = availabilityFixture('Grower', withCycle: false);
    $unknown = (string) Str::uuid7();

    $this->actingAs($account)
        ->from("/producer/cycles/{$unknown}/products/{$jam}/availability")
        ->put("/producer/cycles/{$unknown}/products/{$jam}/availability", ['available' => false])
        ->assertSessionHasErrors('domain');
});
