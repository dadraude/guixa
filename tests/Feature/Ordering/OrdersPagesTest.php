<?php

use Carbon\CarbonImmutable;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('keeps the order pages to members (R7, S33)', function (string $method, string $suffix) {
    [$cycleId, , $loaf] = cycleWithBaker();
    $uri = str_replace('{product}', $loaf, "/cycles/{$cycleId}/order{$suffix}");

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Manager, Role::Producer))->{$method}($uri)->assertForbidden();
})->with([
    ['get', ''],
    ['put', '/lines/{product}'],
    ['delete', '/lines/{product}'],
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

it('excludes and rejects a same-producer stock Catalog ID from the weekly order (R3, S66)', function () {
    [$cycleId, $baker, $loaf] = cycleWithBaker();
    $stock = ProductModel::query()->create([
        'producer_id' => $baker->id, 'stock' => true, 'name' => 'Stock loaf', 'unit' => 'loaf',
        'price_cents' => 350, 'variable' => false, 'active' => true,
    ]);
    $member = accountWith(Role::Member);
    $this->actingAs($member)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.producers.0.products.0.id', $loaf)
            ->has('order.producers.0.products', 1));

    $this->actingAs($member)->put("/cycles/{$cycleId}/order/lines/{$stock->id}", ['quantity' => '1'])
        ->assertSessionHasErrors('domain');
    expect(DB::table('order_lines')->where('product_id', $stock->id)->count())->toBe(0);

    $this->actingAs($member)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '1'])->assertRedirect();
    expect(DB::table('order_lines')->pluck('product_id')->all())->toBe([$loaf]);
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

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page->has('order.lines', 0));
    $this->actingAs($anna)->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('cycles.0.lineCount', 0));
    expect(DB::table('order_lines')->whereNull('removed_at')->count())->toBe(0);
    expect(DB::table('order_lines')->where('product_id', $loaf)->whereNotNull('removed_at')->count())->toBe(1);
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

it('marks the line of a deactivated product, which the member removes after the cutoff (R3b, R8, S32)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2']);
    DB::table('products')->where('id', $loaf)->update(['active' => false]);
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['closes_at' => CarbonImmutable::now('UTC')->subMinute()->toDateTimeString()]);

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.producers.0.open', false)
            ->has('order.producers.0.products', 0)
            ->where('order.lines.0.notDelivered', true));

    $this->actingAs($anna)->delete("/cycles/{$cycleId}/order/lines/{$loaf}")->assertRedirect();

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page->has('order.lines', 0));
    $this->actingAs($anna)->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('cycles.0.lineCount', 0));
    expect(DB::table('order_lines')->whereNull('removed_at')->count())->toBe(0);
    expect(DB::table('order_lines')->where('product_id', $loaf)->whereNotNull('removed_at')->count())->toBe(1);
});

it('marks a line whose product is unavailable in the cycle and stops offering the product (R3b, R3c, S48)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2']);
    DB::table('product_unavailabilities')->insert(['cycle_id' => $cycleId, 'product_id' => $loaf]);

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page
            ->has('order.producers.0.products', 0)
            ->where('order.lines.0.notDelivered', true));

    DB::table('product_unavailabilities')->delete();

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page
            ->has('order.producers.0.products', 1)
            ->where('order.lines.0.notDelivered', false));
});

it('marks the line of a producer that does not take part any more (R6c)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2']);
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['participating' => false]);

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page->where('order.lines.0.notDelivered', true));
});

it('shows the domain error and keeps a line that is not marked (R8)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2']);

    $this->actingAs($anna)
        ->from("/cycles/{$cycleId}/order")
        ->delete("/cycles/{$cycleId}/order/lines/{$loaf}")
        ->assertRedirect("/cycles/{$cycleId}/order")
        ->assertSessionHasErrors('domain');

    expect(DB::table('order_lines')->count())->toBe(1);
});

it('answers with the domain error when the cycle of a marked line is unknown (R3b)', function () {
    [, , $loaf] = cycleWithBaker();
    $cycleId = (string) Str::uuid7();

    $this->actingAs(accountWith(Role::Member))
        ->from("/cycles/{$cycleId}/order")
        ->delete("/cycles/{$cycleId}/order/lines/{$loaf}")
        ->assertSessionHasErrors('domain');
});

it('shows the order line of a product that is orderable as not marked (R3b)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2']);

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page->where('order.lines.0.notDelivered', false));
});

it('lists the cycles on the home page of a member, open ones first (R4a, R23a)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2']);

    $this->actingAs($anna)->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Index')
            ->has('cycles', 1)
            ->where('cycles.0.cycleId', $cycleId)
            ->where('cycles.0.open', true)
            ->where('cycles.0.lineCount', 1));
});

it('keeps the welcome page for a member whose email is not verified', function () {
    $anna = accountWith(Role::Member);
    $anna->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($anna)->get('/')->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

it('keeps the welcome page for guests and for accounts that are not members', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page->component('Welcome'));
    $this->actingAs(accountWith(Role::Manager))->get('/')->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

it('tells the member the cycle is cancelled and shows no lines (R4b, S37)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2'])->assertRedirect();
    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page->where('order.cancelled', false));

    $this->actingAs(accountWith(Role::Manager))->post("/manage/cycles/{$cycleId}/cancel")->assertRedirect();

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page->where('order.cancelled', true)->has('order.lines', 0));
});

it('tells the home page which listed cycles are cancelled (R23a)', function () {
    [$cycleId] = cycleWithBaker();
    $anna = accountWith(Role::Member);
    $this->actingAs($anna)->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('cycles.0.cancelled', false));

    $this->actingAs(accountWith(Role::Manager))->post("/manage/cycles/{$cycleId}/cancel")->assertRedirect();

    $this->actingAs($anna)->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('cycles.0.cancelled', true)->where('cycles.0.open', false));
});

it('prices the saved quantities and the ones being typed on the server (R7)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $anna = accountWith(Role::Member);

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.totalCents', 0)
            ->where("preview.amountCents.{$loaf}", 0));

    $this->actingAs($anna)->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '2'])->assertRedirect();
    $this->actingAs($anna)->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page->where('preview.totalCents', 700));

    $this->actingAs($anna)->get("/cycles/{$cycleId}/order?typed[{$loaf}]=1.5")
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.totalCents', 525)
            ->where("preview.amountCents.{$loaf}", 525));
});

it('counts a typed text that is not a quantity as nothing (R9)', function (string $typed) {
    [$cycleId, , $loaf] = cycleWithBaker();

    $this->actingAs(accountWith(Role::Member))->get("/cycles/{$cycleId}/order?typed[{$loaf}]=".urlencode($typed))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('preview.totalCents', 0));
})->with(['', '-1', '1.2345', 'abc', '1,5']);
