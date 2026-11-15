<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** Q's "Tomàquet" and a cycle C in which Q takes part. */
function availabilityScenario(): array
{
    $q = ProducerModel::query()->create(['name' => 'Q', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $tomato = ProductModel::query()->create([
        'producer_id' => $q->id, 'name' => 'Tomàquet', 'unit' => 'kg', 'price_cents' => 300, 'variable' => false, 'active' => true, 'stock' => false,
    ]);
    $cycleId = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $cycleId, 'pickup_date' => now()->addDays(30)->toDateString()]);
    DB::table('cycle_producers')->insert(['cycle_id' => $cycleId, 'producer_id' => $q->id, 'participating' => true]);

    return [$q, $tomato, $cycleId];
}

function availabilityUri(ProducerModel $producer, string $cycleId, ProductModel $product): string
{
    return "/catalogue/producers/{$producer->id}/cycles/{$cycleId}/products/{$product->id}/availability";
}

// R14 — closed to every other role

it('closes the availability routes to accounts without Manager or Catalogue (R14)', function (?Role $role) {
    [$q, $tomato, $cycleId] = availabilityScenario();
    $account = $role === null ? accountWith(Role::Member) : accountWith($role);

    $this->actingAs($account)->get("/catalogue/producers/{$q->id}/availability")->assertForbidden();
    $this->actingAs($account)->put(availabilityUri($q, $cycleId, $tomato), ['available' => false])->assertForbidden();
    expect(DB::table('product_unavailabilities')->count())->toBe(0);
})->with([Role::Receiver, Role::Finance, null]);

it('sends guests to the login page (R14)', function () {
    [$q, $tomato, $cycleId] = availabilityScenario();

    $this->get("/catalogue/producers/{$q->id}/availability")->assertRedirect(route('login'));
    $this->put(availabilityUri($q, $cycleId, $tomato), ['available' => false])->assertRedirect(route('login'));
});

// R3, R5, S13

it('lets Catalogue mark Q\'s product unavailable in C so a member cannot newly order it, while P stays deactivated and administrable (R3, R5, S13)', function () {
    [$q, $tomato, $cycleId] = availabilityScenario();
    $p = ProducerModel::query()->create(['name' => 'P', 'active' => false, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $pProduct = ProductModel::query()->create([
        'producer_id' => $p->id, 'name' => 'Pebrot', 'unit' => 'kg', 'price_cents' => 200, 'variable' => false, 'active' => true, 'stock' => false,
    ]);
    $catalogue = accountWith(Role::Catalogue);

    $this->actingAs($catalogue)->get('/catalogue/producers')->assertInertia(fn (Assert $page) => $page
        ->where('producers.1.name', 'Q')->where('producers.0.name', 'P')->where('producers.0.active', false));
    $this->actingAs($catalogue)->get("/catalogue/producers/{$p->id}/availability")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Catalogue/Producers/Availability')->where('producer.active', false));
    $this->actingAs($catalogue)->put("/catalogue/producers/{$p->id}/products/weekly/{$pProduct->id}", weeklyForm(['name' => 'Pebrot', 'price' => '2.50']))->assertRedirect();
    expect($pProduct->fresh()->price_cents)->toBe(250);

    $this->actingAs($catalogue)->get("/catalogue/producers/{$q->id}/availability")->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalogue/Producers/Availability')
            ->where('producer.name', 'Q')
            ->where('cycles.0.cycleId', $cycleId)
            ->where('cycles.0.canChange', true)
            ->where('cycles.0.products.0.productId', $tomato->id)
            ->where('cycles.0.products.0.available', true)
            ->missing('cycles.0.totals')->missing('cycles.0.products.0.quantity'));

    $this->actingAs($catalogue)->put(availabilityUri($q, $cycleId, $tomato), ['available' => false])->assertRedirect();
    expect(DB::table('product_unavailabilities')->where('cycle_id', $cycleId)->pluck('product_id')->all())->toBe([$tomato->id]);

    $member = accountWith(Role::Member);
    $this->actingAs($member)->put("/cycles/{$cycleId}/order/lines/{$tomato->id}", ['quantity' => '1'])->assertSessionHasErrors('domain');
    expect(DB::table('order_lines')->count())->toBe(0);

    $this->actingAs($catalogue)->get("/catalogue/producers/{$q->id}/availability")
        ->assertInertia(fn (Assert $page) => $page->where('cycles.0.products.0.available', false));
    $this->actingAs($catalogue)->put(availabilityUri($q, $cycleId, $tomato), ['available' => true])->assertRedirect();
    expect(DB::table('product_unavailabilities')->count())->toBe(0);
});

it('lets the manager mark availability too (R3)', function () {
    [$q, $tomato, $cycleId] = availabilityScenario();

    $this->actingAs(accountWith(Role::Manager))->put(availabilityUri($q, $cycleId, $tomato), ['available' => false])->assertRedirect();

    expect(DB::table('product_unavailabilities')->pluck('product_id')->all())->toBe([$tomato->id]);
});

it('offers no toggle after Q\'s order is sent and rejects a forced request with the sent error (R3c, S13)', function () {
    [$q, $tomato, $cycleId] = availabilityScenario();
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['closes_at' => now()->subHour(), 'sent_at' => now()]);
    $catalogue = accountWith(Role::Catalogue);

    $this->actingAs($catalogue)->get("/catalogue/producers/{$q->id}/availability")
        ->assertInertia(fn (Assert $page) => $page->where('cycles', []));
    $this->actingAs($catalogue)->put(availabilityUri($q, $cycleId, $tomato), ['available' => false])->assertSessionHasErrors('domain');

    expect(DB::table('product_unavailabilities')->count())->toBe(0);
});

it('shows no cycle totals or cycle actions on the availability page (D9, R14)', function () {
    [$q] = availabilityScenario();

    $this->actingAs(accountWith(Role::Catalogue))->get("/catalogue/producers/{$q->id}/availability")
        ->assertInertia(fn (Assert $page) => $page
            ->has('cycles.0', fn (Assert $cycle) => $cycle->hasAll(['cycleId', 'pickupDate', 'canChange', 'products']))
            ->has('producer', fn (Assert $producer) => $producer->hasAll(['id', 'name', 'active'])));
});

it('answers 404 for an unknown producer and for a product of another producer, and validates the flag (R3)', function () {
    [$q, $tomato, $cycleId] = availabilityScenario();
    $other = ProducerModel::query()->create(['name' => 'Other', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $catalogue = accountWith(Role::Catalogue);

    $this->actingAs($catalogue)->get('/catalogue/producers/'.Str::uuid7().'/availability')->assertNotFound();
    $this->actingAs($catalogue)->put(availabilityUri($other, $cycleId, $tomato), ['available' => false])->assertNotFound();
    $this->actingAs($catalogue)->put("/catalogue/producers/{$q->id}/cycles/{$cycleId}/products/".Str::uuid7().'/availability', ['available' => false])->assertNotFound();
    $this->actingAs($catalogue)->put(availabilityUri($q, $cycleId, $tomato), [])->assertSessionHasErrors('available');
    expect(DB::table('product_unavailabilities')->count())->toBe(0);
});

it('answers 404 and writes nothing for an unknown, cancelled or non-participating cycle (D9)', function (string $case) {
    [$q, $tomato, $cycleId] = availabilityScenario();
    $target = match ($case) {
        'unknown' => (string) Str::uuid7(),
        'cancelled' => tap($cycleId, fn () => DB::table('cycles')->where('id', $cycleId)->update(['cancelled_at' => now()])),
        'other producer only' => tap($cycleId, fn () => DB::table('cycle_producers')->where('cycle_id', $cycleId)->delete()),
    };

    $this->actingAs(accountWith(Role::Catalogue))->put(availabilityUri($q, $target, $tomato), ['available' => false])->assertNotFound();

    expect(DB::table('product_unavailabilities')->count())->toBe(0);
})->with(['unknown', 'cancelled', 'other producer only']);
