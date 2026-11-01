<?php

use App\Models\User;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** A cycle with the baker, whose account is a producer; an order of 2 loaves from a household. */
function producerSummaryFixture(): array
{
    [$cycleId, $baker, $loaf] = cycleWithBaker();
    $producer = User::factory()->create(['producer_id' => $baker->id]);
    DB::table('account_roles')->insert(['user_id' => $producer->id, 'role' => Role::Producer->value]);
    $householdId = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $householdId, 'name' => 'Household A', 'created_at' => now(), 'updated_at' => now()]);
    $member = memberOf($householdId, 'Anna');
    test()->actingAs($member)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2'])->assertRedirect();

    app('auth')->forgetGuards();

    return [$producer, $cycleId, $loaf];
}

it('keeps the producer cycle summary to producers (R12)', function () {
    [, $cycleId] = producerSummaryFixture();

    $this->get("/producer/cycles/{$cycleId}")->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Manager))->get("/producer/cycles/{$cycleId}")->assertForbidden();
});

it('shows the products with their availability and no quantities before the order is sent (R12, S8)', function () {
    [$producer, $cycleId, $loaf] = producerSummaryFixture();

    $this->actingAs($producer)->get("/producer/cycles/{$cycleId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Producer/CycleSummary')
            ->where('summary.cycleId', $cycleId)
            ->where('summary.sent', false)
            ->where('summary.products.0.productId', $loaf)
            ->where('summary.products.0.available', true)
            ->where('summary.products.0.quantity', null));
});

it('shows the total per product without household names once the order is sent (R12, S7)', function () {
    [$producer, $cycleId] = producerSummaryFixture();
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['closes_at' => now()->subHour(), 'sent_at' => now()]);

    $this->actingAs($producer)->get("/producer/cycles/{$cycleId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.sent', true)
            ->where('summary.products.0.name', 'Loaf')
            ->where('summary.products.0.quantity', '2.000')
            ->missing('summary.products.0.households'));
});

it('answers 404 for a cycle the producer is not in or that does not exist (R2)', function () {
    [$producer] = producerSummaryFixture();
    $otherCycle = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $otherCycle, 'pickup_date' => now()->addDays(31)->toDateString()]);

    $this->actingAs($producer)->get('/producer/cycles/'.Str::uuid7())->assertNotFound();
    $this->actingAs($producer)->get("/producer/cycles/{$otherCycle}")->assertNotFound();
});
