<?php

use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** A sent baker order: households A and B ordered 4 and 6 loaves, and D is a household that ordered nothing. */
function receptionFixture(): array
{
    [$cycleId, $baker, $loaf] = cycleWithBaker();
    $households = [];
    foreach (['A', 'B', 'D'] as $name) {
        $households[$name] = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $households[$name], 'name' => "Household {$name}", 'created_at' => now(), 'updated_at' => now()]);
    }
    $rye = (string) Str::uuid7();
    DB::table('products')->insert([
        'id' => $rye, 'producer_id' => $baker->id, 'name' => 'Rye', 'unit' => 'loaf', 'price_cents' => 400, 'variable' => false, 'active' => true,
    ]);
    foreach (['A' => '4', 'B' => '6'] as $name => $quantity) {
        test()->actingAs(memberOf($households[$name], "Member {$name}"))->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => $quantity])->assertRedirect();
        app('auth')->forgetGuards();
    }
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['closes_at' => now()->subHour(), 'sent_at' => now()]);

    return [$cycleId, $baker->id, $loaf, $rye, $households, accountWith(Role::Manager)];
}

it('keeps the reception to managers (R14)', function () {
    [$cycleId, $producerId] = receptionFixture();

    $this->get("/manage/cycles/{$cycleId}/receptions/{$producerId}")->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->get("/manage/cycles/{$cycleId}/receptions/{$producerId}")->assertForbidden();
});

it('shows the products with their totals, the households and the products that can be added (R14, R17)', function () {
    [$cycleId, $producerId, $loaf, $rye, $households, $manager] = receptionFixture();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/receptions/{$producerId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Reception/Show')
            ->where('reception.cycleId', $cycleId)
            ->where('reception.producerName', 'Baker')
            ->where('reception.products.0.productId', $loaf)
            ->where('reception.products.0.ordered', '10.000')
            ->where('reception.products.0.received', '10.000')
            ->where('reception.products.0.balanced', true)
            ->where('reception.products.0.allocations.0.householdName', 'Household A')
            ->where('reception.products.0.allocations.0.allocated', '4.000')
            ->where('reception.unorderedOptions.0.productId', $rye)
            ->where('households.2.id', $households['D'])
            ->where('households.2.name', 'Household D'));
});

it('answers 404 for an unknown cycle, a producer not in it or an order that is not sent (R14)', function () {
    [$cycleId, $producerId, , , , $manager] = receptionFixture();
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['sent_at' => null]);

    $this->actingAs($manager)->get('/manage/cycles/'.Str::uuid7()."/receptions/{$producerId}")->assertNotFound();
    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/receptions/".Str::uuid7())->assertNotFound();
    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/receptions/{$producerId}")->assertNotFound();
});

it('sets the received total and re-allocates proportionally (R15, S10)', function () {
    [$cycleId, $producerId, $loaf, , , $manager] = receptionFixture();

    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/receptions/{$producerId}/products/{$loaf}/received", ['received' => '5'])->assertRedirect();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/receptions/{$producerId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('reception.products.0.received', '5.000')
            ->where('reception.products.0.allocations.0.allocated', '2.000')
            ->where('reception.products.0.allocations.1.allocated', '3.000'));
});

it('asks for confirmation before way A overwrites a manual allocation, and overwrites once confirmed (R15, S31)', function () {
    [$cycleId, $producerId, $loaf, , $households, $manager] = receptionFixture();
    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/receptions/{$producerId}/products/{$loaf}/allocations/{$households['B']}", ['quantity' => '5'])->assertRedirect();

    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/receptions/{$producerId}/products/{$loaf}/received", ['received' => '5'])
        ->assertSessionHasErrors('domain');
    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/receptions/{$producerId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('reception.products.0.received', '10.000')
            ->where('reception.products.0.hasManualAllocations', true)
            ->where('reception.products.0.difference', '1.000'));

    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/receptions/{$producerId}/products/{$loaf}/received", ['received' => '5', 'overwrite_manual' => true])->assertRedirect();
    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/receptions/{$producerId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('reception.products.0.received', '5.000')
            ->where('reception.products.0.hasManualAllocations', false)
            ->where('reception.products.0.balanced', true));
});

it('allocates by hand to a household that did not order, taking a snapshot of the product (R16, S39)', function () {
    [$cycleId, $producerId, $loaf, , $households, $manager] = receptionFixture();

    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/receptions/{$producerId}/products/{$loaf}/allocations/{$households['D']}", ['quantity' => '1'])->assertRedirect();

    $this->assertDatabaseHas('order_lines', ['product_id' => $loaf, 'ordered_quantity' => '0.000', 'allocated_quantity' => '1.000', 'allocated_by_hand' => true, 'unit_price_cents' => 350]);
    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/receptions/{$producerId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('reception.products.0.difference', '-1.000')
            ->where('reception.products.0.allocations.2.householdName', 'Household D'));
});

it('refuses an allocation to a deactivated household (R16)', function () {
    [$cycleId, $producerId, $loaf, , $households, $manager] = receptionFixture();
    DB::table('households')->where('id', $households['D'])->update(['deactivated_at' => now()]);

    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/receptions/{$producerId}/products/{$loaf}/allocations/{$households['D']}", ['quantity' => '1'])
        ->assertSessionHasErrors('domain');
});

it('adds a product nobody ordered with its received total and allocates it (R14, R16, S40)', function () {
    [$cycleId, $producerId, , $rye, $households, $manager] = receptionFixture();

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/receptions/{$producerId}/products", ['product_id' => $rye, 'received' => '3'])->assertRedirect();
    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/receptions/{$producerId}/products/{$rye}/allocations/{$households['A']}", ['quantity' => '2'])->assertRedirect();
    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/receptions/{$producerId}/products/{$rye}/allocations/{$households['B']}", ['quantity' => '1'])->assertRedirect();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/receptions/{$producerId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('reception.products.1.name', 'Rye')
            ->where('reception.products.1.nobodyOrdered', true)
            ->where('reception.products.1.received', '3.000')
            ->where('reception.products.1.balanced', true)
            ->has('reception.unorderedOptions', 0));
});

it('keeps the quantities the manager types to valid fields (R15, R16)', function () {
    [$cycleId, $producerId, $loaf, , $households, $manager] = receptionFixture();

    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/receptions/{$producerId}/products/{$loaf}/received", [])->assertSessionHasErrors('received');
    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/receptions/{$producerId}/products/{$loaf}/allocations/{$households['A']}", ['quantity' => '-1'])
        ->assertSessionHasErrors('domain');
    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/receptions/{$producerId}/products", [])->assertSessionHasErrors('product_id');
});
