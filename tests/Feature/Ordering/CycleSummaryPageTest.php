<?php

use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function householdNamed(string $name): string
{
    $id = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $id, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);

    return $id;
}

it('keeps the cycle summary to managers (R13)', function () {
    [$cycleId] = cycleWithBaker();

    $this->get("/manage/cycles/{$cycleId}")->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->get("/manage/cycles/{$cycleId}")->assertForbidden();
});

it('shows the totals per producer and product with the breakdown per household (R13, S52)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = memberOf(householdNamed('Household A'), 'Anna');
    $biel = memberOf(householdNamed('Household B'), 'Biel');
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2'])->assertRedirect();
    $this->actingAs($biel)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '3'])->assertRedirect();

    $this->actingAs(accountWith(Role::Manager))->get("/manage/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Cycles/Show')
            ->where('summary.cycleId', $cycleId)
            ->where('summary.producers.0.producerName', 'Baker')
            ->where('summary.producers.0.products.0.name', 'Loaf')
            ->where('summary.producers.0.products.0.quantity', '5.000')
            ->where('summary.producers.0.products.0.households.0.householdName', 'Household A')
            ->where('summary.producers.0.products.0.households.0.quantity', '2.000')
            ->where('summary.producers.0.products.0.households.1.householdName', 'Household B')
            ->where('summary.producers.0.products.0.households.1.quantity', '3.000'));
});

it('answers 404 for the summary of an unknown cycle (R13)', function () {
    $this->actingAs(accountWith(Role::Manager))->get('/manage/cycles/'.Str::uuid7())->assertNotFound();
});

it('flags a cancelled cycle and shows no totals (R4b, R13, S62)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = memberOf(householdNamed('Household A'), 'Anna');
    $manager = accountWith(Role::Manager);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2'])->assertRedirect();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page->where('summary.cancelled', false)->has('summary.producers', 1));

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/cancel")->assertRedirect();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Cycles/Show')
            ->where('summary.cancelled', true)
            ->has('summary.producers', 0)
            ->where('containers.total', 0)
            ->has('containers.households', 0));
});

it('shows the household and cycle container counts from the application query (R18a, S60)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    DB::table('products')->where('id', $loaf)->update(['containers_per_unit' => '1.000']);
    $household = householdNamed('Household A');
    $member = memberOf($household, 'Anna');
    $this->actingAs($member)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2'])->assertRedirect();
    DB::table('products')->where('id', $loaf)->update(['containers_per_unit' => '4.000']);

    $this->actingAs(accountWith(Role::Manager))->get("/manage/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('containers.total', 2)
            ->where('containers.households.0.householdName', 'Household A')
            ->where('containers.households.0.count', 2));
});
