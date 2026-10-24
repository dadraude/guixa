<?php

use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** A settled cycle where household A has a pending pickup of 4 loaves at €3.50, and household K has no order. */
function pickupFixture(): array
{
    [$cycleId, $baker, $loaf] = cycleWithBaker();
    $manager = accountWith(Role::Manager);
    $households = [];
    foreach (['A', 'K'] as $name) {
        $households[$name] = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $households[$name], 'name' => "Household {$name}", 'created_at' => now(), 'updated_at' => now()]);
    }
    DB::table('cycles')->where('id', $cycleId)->update(['settled_at' => now()]);
    $orderId = (string) Str::uuid7();
    DB::table('orders')->insert(['id' => $orderId, 'cycle_id' => $cycleId, 'household_id' => $households['A'], 'pickup_status' => 'pending']);
    DB::table('order_lines')->insert([
        'id' => (string) Str::uuid7(), 'order_id' => $orderId, 'product_id' => $loaf, 'producer_id' => $baker->id, 'product_name' => 'Loaf',
        'unit' => 'loaf', 'unit_price_cents' => 350, 'variable' => false, 'ordered_quantity' => '4', 'allocated_quantity' => '4',
        'created_at' => now(), 'added_by' => $manager->id,
    ]);

    return [$cycleId, $loaf, $households, $manager];
}

it('keeps the pickups to managers (R19)', function () {
    [$cycleId, , $households] = pickupFixture();

    $this->get("/manage/cycles/{$cycleId}/pickups")->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member))->get("/manage/cycles/{$cycleId}/pickups/{$households['A']}")->assertForbidden();
});

it('lists the pickups and the active households that have none (R19, S18)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Pickups/Index')
            ->where('cycleId', $cycleId)
            ->where('pickups.0.householdName', 'Household A')
            ->where('pickups.0.status', 'pending')
            ->where('pickups.0.totalCents', 1400)
            ->has('pickups', 1)
            ->where('households', fn ($options) => collect($options)->pluck('id')->contains($households['K']) && ! collect($options)->pluck('id')->contains($households['A'])));
});

it('shows a pickup with its lines and total, and answers 404 before settlement or for a household without one (R19, S27)', function () {
    [$cycleId, $loaf, $households, $manager] = pickupFixture();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups/{$households['A']}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Pickups/Show')
            ->where('pickup.householdName', 'Household A')
            ->where('pickup.lines.0.productId', $loaf)
            ->where('pickup.lines.0.delivered', '4.000')
            ->where('pickup.lines.0.amountCents', 1400)
            ->where('pickup.totalCents', 1400));

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups/{$households['K']}")->assertNotFound();

    DB::table('cycles')->where('id', $cycleId)->update(['settled_at' => null]);
    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups")->assertNotFound();
    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups/{$households['A']}")->assertNotFound();
});

it('shows the household balance and the resulting balance (R21)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();
    $this->actingAs($manager)->post("/manage/households/{$households['A']}/wallet/top-ups", ['amount' => '5.00'])->assertRedirect();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups/{$households['A']}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('pickup.balanceCents', 500)
            ->where('pickup.resultingBalanceCents', -900));
});

it('adjusts the delivered quantity of a line, keeping the allocation (R19, S14)', function () {
    [$cycleId, $loaf, $households, $manager] = pickupFixture();

    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/pickups/{$households['A']}/lines/{$loaf}", ['quantity' => '3'])->assertRedirect();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups/{$households['A']}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('pickup.lines.0.allocated', '4.000')
            ->where('pickup.lines.0.delivered', '3.000')
            ->where('pickup.lines.0.adjusted', true)
            ->where('pickup.totalCents', 1050));
});

it('rejects a negative delivered quantity with a form error (R19)', function () {
    [$cycleId, $loaf, $households, $manager] = pickupFixture();

    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/pickups/{$households['A']}/lines/{$loaf}", ['quantity' => '-1'])
        ->assertSessionHasErrors('domain');
});

it('opens the pickup of a household without one and redirects to it (R19, S18)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['K']}")
        ->assertRedirect("/manage/cycles/{$cycleId}/pickups/{$households['K']}");

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups/{$households['K']}")
        ->assertInertia(fn (Assert $page) => $page->where('pickup.status', 'pending')->where('pickup.totalCents', 0));
});

it('shows a member the pickup of their household once the cycle is settled, without the balance (R23a)', function () {
    [$cycleId, $loaf, $households, $manager] = pickupFixture();
    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/pickups/{$households['A']}/lines/{$loaf}", ['quantity' => '3'])->assertRedirect();
    app('auth')->forgetGuards();
    $member = memberOf($households['A']);

    $this->actingAs($member)->get("/cycles/{$cycleId}/order")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.pickup.status', 'pending')
            ->where('order.pickup.lines.0.ordered', '4.000')
            ->where('order.pickup.lines.0.delivered', '3.000')
            ->where('order.pickup.lines.0.unitPriceCents', 350)
            ->where('order.pickup.lines.0.amountCents', 1050)
            ->where('order.pickup.totalCents', 1050)
            ->where('order.pickup.balanceCents', null));

    DB::table('cycles')->where('id', $cycleId)->update(['settled_at' => null]);
    $this->actingAs($member)->get("/cycles/{$cycleId}/order")->assertInertia(fn (Assert $page) => $page->where('order.pickup', null));
});
