<?php

use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('moves Anna to J with her own login while H keeps its order, balance and history with Biel, then lists H to settle (R35, R35a, S43)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $h = (string) Str::uuid7();
    $j = (string) Str::uuid7();
    foreach ([$h => 'Household H', $j => 'Household J'] as $id => $name) {
        DB::table('households')->insert(['id' => $id, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
    }
    $anna = memberOf($h, 'Anna');
    $biel = memberOf($h, 'Biel');
    $manager = accountWith(Role::Manager);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2'])->assertRedirect();
    $this->actingAs($manager)->post("/manage/households/{$h}/wallet/top-ups", ['amount' => '12.00'])->assertRedirect();
    $this->post("/manage/households/{$j}/wallet/top-ups", ['amount' => '3.00'])->assertRedirect();

    $this->put("/manage/accounts/{$anna->id}/household", ['household_id' => $j])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($anna->fresh())->get('/wallet')->assertInertia(fn (Assert $page) => $page->where('wallet.balance', '3.00'));
    $this->get("/cycles/{$cycleId}/order")->assertInertia(fn (Assert $page) => $page->has('order.lines', 0));
    $this->actingAs($biel)->get('/wallet')->assertInertia(fn (Assert $page) => $page->where('wallet.balance', '12.00'));
    $this->get("/cycles/{$cycleId}/order")->assertInertia(fn (Assert $page) => $page->has('order.lines', 1)->where('order.lines.0.quantity', '2.000'));
    expect(DB::table('order_lines')->join('orders', 'orders.id', '=', 'order_lines.order_id')->where('orders.household_id', $h)->value('order_lines.added_by'))->toBe($anna->id)
        ->and(DB::table('households')->where('id', $h)->value('deactivated_at'))->toBeNull()
        ->and(DB::table('users')->where('id', $anna->id)->value('deactivated_at'))->toBeNull();

    $this->actingAs($manager)->delete("/manage/accounts/{$biel->id}/household")->assertRedirect();

    expect(DB::table('households')->where('id', $h)->value('deactivated_at'))->not->toBeNull();
    $this->get('/manage/households')->assertInertia(fn (Assert $page) => $page
        ->where('toSettle.0.householdId', $h)->where('toSettle.0.balanceCents', 1200));
    $this->actingAs($biel->fresh())->get('/wallet')->assertForbidden();
    expect(DB::table('order_lines')->join('orders', 'orders.id', '=', 'order_lines.order_id')->where('orders.household_id', $h)->count())->toBe(1);
});
