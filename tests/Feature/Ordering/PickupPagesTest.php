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

/** A stock product with `$available` on hand, and a return item of €0.50. @return array{string, string} */
function stockAndReturnFixture(string $available = '5'): array
{
    $honey = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $honey, 'producer_id' => DB::table('producers')->value('id'), 'stock' => true, 'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 600, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert([
        'id' => (string) Str::uuid7(), 'stock_product_id' => $honey, 'type' => 'entry', 'quantity' => $available,
        'occurred_at' => now(), 'recorded_by' => (string) Str::uuid7(),
    ]);
    $jar = (string) Str::uuid7();
    DB::table('return_items')->insert(['id' => $jar, 'name' => 'Glass jar', 'amount_cents' => 50, 'active' => true]);

    return [$honey, $jar];
}

it('adds stock to a pickup, and the total includes it (R20, S16)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();
    [$honey] = stockAndReturnFixture();

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/stock-lines", ['stock_product_id' => $honey, 'stock_quantity' => '2'])->assertRedirect();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups/{$households['A']}")
        ->assertInertia(fn (Assert $page) => $page->where('pickup.totalCents', 1400 + 1200));
    expect(DB::table('order_stock_lines')->where('stock_product_id', $honey)->value('quantity'))->toBe('2.000');
});

it('rejects stock above what is available with a form error and stores nothing (R20, S17)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();
    [$honey] = stockAndReturnFixture('1');

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/stock-lines", ['stock_product_id' => $honey, 'stock_quantity' => '2'])
        ->assertSessionHasErrors('domain');

    expect(DB::table('order_stock_lines')->count())->toBe(0);
});

it('removes a stock line (R20)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();
    [$honey] = stockAndReturnFixture();
    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/stock-lines", ['stock_product_id' => $honey, 'stock_quantity' => '2']);

    $this->actingAs($manager)->delete("/manage/cycles/{$cycleId}/pickups/{$households['A']}/stock-lines/{$honey}")->assertRedirect();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups/{$households['A']}")
        ->assertInertia(fn (Assert $page) => $page->has('pickup.stockLines', 0)->where('pickup.totalCents', 1400));
    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups")
        ->assertInertia(fn (Assert $page) => $page->where('pickups.0.totalCents', 1400));
    expect(DB::table('order_stock_lines')->whereNull('removed_at')->count())->toBe(0);
    expect(DB::table('order_stock_lines')->where('stock_product_id', $honey)->whereNotNull('removed_at')->count())->toBe(1);
});

it('adds and removes returned items, crediting them in the total (R20a, S41)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();
    [, $jar] = stockAndReturnFixture();

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/return-lines", ['return_item_id' => $jar, 'return_quantity' => '4'])->assertRedirect();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups/{$households['A']}")
        ->assertInertia(fn (Assert $page) => $page->where('pickup.totalCents', 1400 - 200));

    $this->actingAs($manager)->delete("/manage/cycles/{$cycleId}/pickups/{$households['A']}/return-lines/{$jar}")->assertRedirect();

    expect(DB::table('order_return_lines')->count())->toBe(0);
});

it('keeps stock and return line changes to managers (R20, R20a)', function () {
    [$cycleId, , $households] = pickupFixture();
    [$honey, $jar] = stockAndReturnFixture();
    $base = "/manage/cycles/{$cycleId}/pickups/{$households['A']}";

    $this->actingAs(accountWith(Role::Member))->post("{$base}/stock-lines", ['stock_product_id' => $honey, 'stock_quantity' => '1'])->assertForbidden();
    $this->actingAs(accountWith(Role::Member))->post("{$base}/return-lines", ['return_item_id' => $jar, 'return_quantity' => '1'])->assertForbidden();
});

it('shows the stock and return lines with the products and items a manager can add (R20, R20a)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();
    [$honey, $jar] = stockAndReturnFixture();
    DB::table('products')->insert(['id' => (string) Str::uuid7(), 'producer_id' => DB::table('producers')->value('id'), 'stock' => true, 'name' => 'Old honey', 'unit' => 'jar', 'price_cents' => 100, 'variable' => false, 'active' => false]);
    $base = "/manage/cycles/{$cycleId}/pickups/{$households['A']}";
    $this->actingAs($manager)->post("{$base}/stock-lines", ['stock_product_id' => $honey, 'stock_quantity' => '2']);
    $this->actingAs($manager)->post("{$base}/return-lines", ['return_item_id' => $jar, 'return_quantity' => '4']);

    $this->actingAs($manager)->get($base)
        ->assertInertia(fn (Assert $page) => $page
            ->where('pickup.stockLines.0.stockProductId', $honey)
            ->where('pickup.stockLines.0.quantity', '2.000')
            ->where('pickup.stockLines.0.amountCents', 1200)
            ->where('pickup.returnLines.0.returnItemId', $jar)
            ->where('pickup.returnLines.0.creditCents', 200)
            ->has('stockProducts', 1)
            ->where('stockProducts.0.id', $honey)
            ->where('stockProducts.0.available', '5.000')
            ->where('returnItems.0.id', $jar)
            ->where('returnItems.0.amountCents', 50));
});

it('shows a member the stock and return lines of their pickup, which the total includes (R22, R23a)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();
    [$honey, $jar] = stockAndReturnFixture();
    $base = "/manage/cycles/{$cycleId}/pickups/{$households['A']}";
    $this->actingAs($manager)->post("{$base}/stock-lines", ['stock_product_id' => $honey, 'stock_quantity' => '2']);
    $this->actingAs($manager)->post("{$base}/return-lines", ['return_item_id' => $jar, 'return_quantity' => '4']);
    app('auth')->forgetGuards();

    $this->actingAs(memberOf($households['A']))->get("/cycles/{$cycleId}/order")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.pickup.stockLines.0.name', 'Honey')
            ->where('order.pickup.stockLines.0.amountCents', 1200)
            ->where('order.pickup.returnLines.0.creditCents', 200)
            ->where('order.pickup.totalCents', 1400 + 1200 - 200));
});

it('answers a return quantity that is not a whole number with a visible quantity error, not a 500 (R20a)', function (string $quantity) {
    [$cycleId, , $households, $manager] = pickupFixture();
    [, $jar] = stockAndReturnFixture();

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/return-lines", ['return_item_id' => $jar, 'return_quantity' => $quantity])
        ->assertSessionHasErrors('return_quantity')
        ->assertSessionDoesntHaveErrors(['quantity', 'stock_quantity']);

    expect(DB::table('order_return_lines')->count())->toBe(0);
})->with(['1.5', '1,5', 'abc', '0', '-2', '99999999999', '100001']);

it('answers a stock quantity that is out of range with a visible error, not a 500 (R20)', function (string $quantity) {
    [$cycleId, , $households, $manager] = pickupFixture();
    [$honey] = stockAndReturnFixture();

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/stock-lines", ['stock_product_id' => $honey, 'stock_quantity' => $quantity])
        ->assertSessionHasErrors()
        ->assertSessionDoesntHaveErrors(['quantity', 'return_quantity']);

    expect(DB::table('order_stock_lines')->count())->toBe(0);
})->with(['99999999999', 'abc', '1,5', '12345678']);

it('completes a pickup, charging the household, and redirects to the pickups index (R22, S15)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/complete")
        ->assertRedirect(route('manage.cycles.pickups.index', ['cycle' => $cycleId]));

    expect(DB::table('orders')->where('household_id', $households['A'])->value('pickup_status'))->toBe('completed')
        ->and(DB::table('orders')->where('household_id', $households['A'])->value('charged_cents'))->toBe(1400)
        ->and(DB::table('wallet_movements')->where('household_id', $households['A'])->pluck('amount_cents')->all())->toBe([-1400]);
});

it('records the top-up typed at the pickup before the charge and sells the stock lines (R21, R22, S16, S19)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();
    [$honey] = stockAndReturnFixture();
    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/stock-lines", ['stock_product_id' => $honey, 'stock_quantity' => '2'])->assertRedirect();

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/complete", ['top_up' => '40,00'])->assertRedirect();

    expect(DB::table('wallet_movements')->where('household_id', $households['A'])->orderBy('amount_cents')->get(['type', 'amount_cents', 'cycle_id'])->map(fn ($m) => [$m->type, $m->amount_cents, $m->cycle_id])->all())
        ->toBe([['charge', -2600, $cycleId], ['top_up', 4000, null]])
        ->and(DB::table('stock_movements')->where('stock_product_id', $honey)->where('type', 'sale')->value('quantity'))->toBe('-2.000');
});

it('rejects a second completion with a form error and charges once (R23, S22)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();
    $url = "/manage/cycles/{$cycleId}/pickups/{$households['A']}/complete";
    $this->actingAs($manager)->post($url)->assertRedirect();

    $this->actingAs($manager)->post($url, ['top_up' => '10.00'])
        ->assertSessionHasErrors(['domain' => 'Aquesta recollida ja no està pendent: no es pot modificar ni tornar a completar.']);

    expect(DB::table('wallet_movements')->count())->toBe(1);
});

it('rejects a stock line that exceeds the stock at completion and records nothing (R22, S45)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();
    [$honey] = stockAndReturnFixture('2');
    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/stock-lines", ['stock_product_id' => $honey, 'stock_quantity' => '2'])->assertRedirect();
    DB::table('stock_movements')->insert([
        'id' => (string) Str::uuid7(), 'stock_product_id' => $honey, 'type' => 'adjustment', 'quantity' => '-1', 'note' => 'broken jar',
        'occurred_at' => now(), 'recorded_by' => $manager->id,
    ]);

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/complete", ['top_up' => '10.00'])->assertSessionHasErrors('domain');

    expect(DB::table('wallet_movements')->count())->toBe(0)
        ->and(DB::table('orders')->where('household_id', $households['A'])->value('pickup_status'))->toBe('pending');
});

it('rejects a top-up of zero with the Catalan message and completes nothing (R29)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/complete", ['top_up' => '0'])
        ->assertSessionHasErrors(['domain' => 'L’ingrés ha de ser un import superior a zero.']);

    expect(DB::table('wallet_movements')->count())->toBe(0)
        ->and(DB::table('orders')->where('household_id', $households['A'])->value('pickup_status'))->toBe('pending');
});

it('rejects a top-up that is not an amount (R29)', function (string $topUp) {
    [$cycleId, , $households, $manager] = pickupFixture();

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/complete", ['top_up' => $topUp])->assertSessionHasErrors();

    expect(DB::table('wallet_movements')->count())->toBe(0);
})->with(['abc', '1.234']);

it('keeps completing a pickup to managers (R22)', function () {
    [$cycleId, , $households] = pickupFixture();

    $this->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/complete")->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member))->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/complete")->assertForbidden();
});

it('shows a completed pickup as completed with its frozen lines read-only (R23)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();
    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/pickups/{$households['A']}/complete")->assertRedirect();

    $this->actingAs($manager)->get("/manage/cycles/{$cycleId}/pickups/{$households['A']}")
        ->assertInertia(fn (Assert $page) => $page->where('pickup.status', 'completed')->where('pickup.totalCents', 1400));
});

it('marks a pickup not collected through a manager-only endpoint and exposes closed read-only pages (R24a, R24b)', function () {
    [$cycleId, , $households, $manager] = pickupFixture();
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['sent_at' => now(), 'validated_at' => now(), 'distributed_at' => now()]);
    $url = "/manage/cycles/{$cycleId}/pickups/{$households['A']}/not-collected";
    $this->actingAs(accountWith(Role::Member))->post($url)->assertForbidden();
    $this->actingAs($manager)->post($url)->assertRedirect(route('manage.cycles.pickups.index', $cycleId));
    $this->get("/manage/cycles/{$cycleId}/pickups/{$households['A']}")->assertInertia(fn (Assert $page) => $page->where('pickup.status', 'not_collected'));
    $this->get("/manage/cycles/{$cycleId}/pickups")->assertInertia(fn (Assert $page) => $page->where('closed', true)->has('households', 0));
})->group('R24a', 'R24b');

it('shows original charges and separate redistribution adjustment lines to managers and members (S64)', function () {
    [$cycleId, $product, $households, $manager] = pickupFixture();
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['sent_at' => now(), 'validated_at' => now(), 'distributed_at' => now()]);
    $producer = DB::table('cycle_producers')->where('cycle_id', $cycleId)->value('producer_id');
    $base = "/manage/cycles/{$cycleId}";
    $this->actingAs($manager)->post("{$base}/pickups/{$households['K']}")->assertRedirect();
    $this->post("{$base}/pickups/{$households['A']}/complete")->assertRedirect();
    $this->delete("{$base}/receptions/{$producer}/distribution")->assertRedirect();
    $this->put("{$base}/receptions/{$producer}/products/{$product}/received", ['received' => '6', 'include_completed' => true])->assertRedirect();
    $this->get("{$base}/pickups/{$households['A']}")->assertInertia(fn (Assert $page) => $page
        ->where('pickup.lines.0.delivered', '4.000')->where('pickup.originalChargeCents', 1400)
        ->where('pickup.totalCents', 2100)->where('pickup.adjustments.0.quantityDelta', '2.000')
        ->where('pickup.adjustments.0.amountCents', 700)->where('pickup.resultingBalanceCents', -2100));
    $this->actingAs(memberOf($households['A']))->get("/cycles/{$cycleId}/order")->assertInertia(fn (Assert $page) => $page
        ->where('order.pickup.originalChargeCents', 1400)->where('order.pickup.adjustments.0.amountCents', 700));
})->group('S64');
