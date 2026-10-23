<?php

use Carbon\CarbonImmutable;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** A cycle in a month, with a baker taking part (open until its cutoff, or the pickup date when it has none) and one loaf product. */
function cycleWithBaker(?CarbonImmutable $cutoff = null): array
{
    $baker = ProducerModel::query()->create([
        'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
    ]);
    $loaf = (string) Str::uuid7();
    DB::table('products')->insert([
        'id' => $loaf, 'producer_id' => $baker->id, 'name' => 'Loaf', 'unit' => 'loaf', 'price_cents' => 350, 'variable' => false, 'active' => true,
    ]);
    $cycleId = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $cycleId, 'pickup_date' => CarbonImmutable::now()->addDays(30)->toDateString()]);
    DB::table('cycle_producers')->insert([
        'cycle_id' => $cycleId, 'producer_id' => $baker->id, 'participating' => true, 'closes_at' => $cutoff?->toDateTimeString(),
    ]);

    return [$cycleId, $baker, $loaf];
}

it('keeps the order pages to members (R7, S33)', function (string $method, string $suffix) {
    [$cycleId, , $loaf] = cycleWithBaker();
    $uri = str_replace('{product}', $loaf, "/cycles/{$cycleId}/order{$suffix}");

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Manager, Role::Producer))->{$method}($uri)->assertForbidden();
})->with([
    ['get', ''],
    ['put', '/lines/{product}'],
]);

it('shows the order with what each participating producer offers (R3, R7, S4)', function () {
    [$cycleId, $baker, $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2'])->assertRedirect();

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Edit')
            ->where('order.cycleId', $cycleId)
            ->where('order.producers.0.producerName', 'Baker')
            ->where('order.producers.0.open', true)
            ->where('order.producers.0.products.0.name', 'Loaf')
            ->where('order.producers.0.products.0.price', '3.50')
            ->has('order.lines', 1)
            ->where('order.lines.0.productId', $loaf)
            ->where('order.lines.0.name', 'Loaf')
            ->where('order.lines.0.price', '3.50')
            ->where('order.lines.0.quantity', '2.000'));
});

it('lets another member of the household see and change the same order (R7, S4)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $biel = memberOf($anna->refresh()->household_id, 'Biel');
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2']);

    $this->actingAs($biel)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '3'])->assertRedirect();

    $this->actingAs($biel)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page->has('order.lines', 1)->where('order.lines.0.quantity', '3.000'));
    expect(DB::table('orders')->count())->toBe(1);
});

it('removes a line when the quantity is zero (R9)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2']);

    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '0'])->assertRedirect();

    expect(DB::table('order_lines')->count())->toBe(0);
});

it('shows the domain error for a closed producer and stores nothing (R8, S2)', function () {
    [$cycleId, , $loaf] = cycleWithBaker(CarbonImmutable::now('UTC')->subMinute());

    $this->actingAs(accountWith(Role::Member))
        ->from("/cycles/{$cycleId}/order")
        ->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2'])
        ->assertRedirect("/cycles/{$cycleId}/order")
        ->assertSessionHasErrors('domain');

    expect(DB::table('order_lines')->count())->toBe(0);
});

it('shows the domain error for a fraction of a whole-unit product and for too many decimals (R9, S49)', function (string $quantity) {
    [$cycleId, , $loaf] = cycleWithBaker();

    $this->actingAs(accountWith(Role::Member))
        ->from("/cycles/{$cycleId}/order")
        ->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => $quantity])
        ->assertSessionHasErrors('domain');
})->with(['half a loaf' => '0.5', 'ten thousandths' => '0.0005']);

it('validates that a quantity is given (R9)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();

    $this->actingAs(accountWith(Role::Member))
        ->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => ''])
        ->assertSessionHasErrors('quantity');
});

it('answers with the domain error when the cycle is unknown (R7)', function () {
    [, , $loaf] = cycleWithBaker();
    $cycleId = (string) Str::uuid7();

    $this->actingAs(accountWith(Role::Member))
        ->from("/cycles/{$cycleId}/order")
        ->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '1'])
        ->assertSessionHasErrors('domain');
});

it('keeps showing a line as it was ordered after the product changes (R10, S5)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2']);

    DB::table('products')->where('id', $loaf)->update(['name' => 'Loaf XL', 'price_cents' => 400]);

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.lines.0.name', 'Loaf')
            ->where('order.lines.0.price', '3.50')
            ->where('order.producers.0.products.0.name', 'Loaf XL')
            ->where('order.producers.0.products.0.price', '4.00'));
});

it('answers 404 for the order of an unknown cycle (R7)', function () {
    $this->actingAs(accountWith(Role::Member))
        ->get('/cycles/'.Str::uuid7().'/order')
        ->assertNotFound();
});

it('names a producer that no longer takes part in the cycle when the order has a line of it (R6c)', function () {
    [$cycleId, $baker, $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2']);
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['participating' => false]);

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page
            ->has('order.producers', 0)
            ->where('order.lines.0.producerId', $baker->id)
            ->where('order.lines.0.producerName', 'Baker'));
});
