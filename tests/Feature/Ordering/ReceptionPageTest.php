<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
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

it('keeps the reception to Manager, Finance and Receiver (R14)', function () {
    [$cycleId, $producerId] = receptionFixture();

    $this->get("/receptions/{$cycleId}/{$producerId}")->assertRedirect(route('login'));
    foreach ([[Role::Member, Role::Producer], [Role::Catalogue]] as $roles) {
        $this->actingAs(accountWith(...$roles))->get("/receptions/{$cycleId}/{$producerId}")->assertForbidden();
        $this->actingAs(accountWith(...$roles))->get('/receptions')->assertForbidden();
    }
});

it('shows the products with their totals, the households and the products that can be added (R14, R17)', function () {
    [$cycleId, $producerId, $loaf, $rye, $households, $manager] = receptionFixture();

    $this->actingAs($manager)->get("/receptions/{$cycleId}/{$producerId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Receptions/Show')
            ->where('reception.cycleId', $cycleId)
            ->where('reception.producerName', 'Baker')
            ->where('reception.products.0.productId', $loaf)
            ->where('reception.products.0.ordered', '10.000')
            ->where('reception.products.0.received', '10.000')
            ->where('reception.products.0.balanced', true)
            ->where('reception.products.0.allocations.0.householdName', 'Household A')
            ->where('reception.products.0.allocations.0.allocated', '4.000')
            ->where('reception.unorderedOptions.0.productId', $rye)
            ->where('households', fn ($options) => collect($options)->contains(fn ($option) => $option === ['id' => $households['D'], 'name' => 'Household D'])));
});

it('excludes and rejects a same-producer stock Catalog ID from sent reception writes (R14-R16, S77)', function () {
    [$cycleId, $producerId, $loaf, $rye, $households, $manager] = receptionFixture();
    $stock = ProductModel::query()->create([
        'producer_id' => $producerId, 'stock' => true, 'name' => 'Stock honey', 'unit' => 'jar',
        'price_cents' => 650, 'variable' => false, 'active' => true,
    ]);
    $uri = "/receptions/{$cycleId}/{$producerId}";

    $this->actingAs($manager)->get($uri)->assertInertia(fn (Assert $page) => $page
        ->has('reception.unorderedOptions', 1)
        ->where('reception.unorderedOptions.0.productId', $rye));
    $this->actingAs($manager)->post("{$uri}/products", ['product_id' => $stock->id, 'received' => '1'])
        ->assertSessionHasErrors('domain');
    $this->actingAs($manager)->put("{$uri}/products/{$stock->id}/received", ['received' => '1'])
        ->assertSessionHasErrors('domain');
    $this->actingAs($manager)->put("{$uri}/products/{$stock->id}/allocations/{$households['D']}", ['quantity' => '1'])
        ->assertSessionHasErrors('domain');
    expect(DB::table('received_totals')->where('product_id', $stock->id)->count())->toBe(0)
        ->and(DB::table('order_lines')->where('product_id', $stock->id)->count())->toBe(0);

    $this->actingAs($manager)->post("{$uri}/products", ['product_id' => $rye, 'received' => '1'])->assertRedirect();
    expect(DB::table('received_totals')->where('product_id', $rye)->count())->toBe(1);
});

it('answers 404 for an unknown cycle, a producer not in it or an order that is not sent (R14)', function () {
    [$cycleId, $producerId, , , , $manager] = receptionFixture();
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['sent_at' => null]);

    $this->actingAs($manager)->get('/receptions/'.Str::uuid7()."/{$producerId}")->assertNotFound();
    $this->actingAs($manager)->get("/receptions/{$cycleId}/".Str::uuid7())->assertNotFound();
    $this->actingAs($manager)->get("/receptions/{$cycleId}/{$producerId}")->assertNotFound();
});

it('sets the received total and re-allocates proportionally (R15, S10)', function () {
    [$cycleId, $producerId, $loaf, , , $manager] = receptionFixture();

    $this->actingAs($manager)->put("/receptions/{$cycleId}/{$producerId}/products/{$loaf}/received", ['received' => '5'])->assertRedirect();

    $this->actingAs($manager)->get("/receptions/{$cycleId}/{$producerId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('reception.products.0.received', '5.000')
            ->where('reception.products.0.allocations.0.allocated', '2.000')
            ->where('reception.products.0.allocations.1.allocated', '3.000'));
});

it('asks for confirmation before way A overwrites a manual allocation, and overwrites once confirmed (R15, S31)', function () {
    [$cycleId, $producerId, $loaf, , $households, $manager] = receptionFixture();
    $this->actingAs($manager)->put("/receptions/{$cycleId}/{$producerId}/products/{$loaf}/allocations/{$households['B']}", ['quantity' => '5'])->assertRedirect();

    $this->actingAs($manager)->put("/receptions/{$cycleId}/{$producerId}/products/{$loaf}/received", ['received' => '5'])
        ->assertSessionHasErrors('domain');
    $this->actingAs($manager)->get("/receptions/{$cycleId}/{$producerId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('reception.products.0.received', '10.000')
            ->where('reception.products.0.hasManualAllocations', true)
            ->where('reception.products.0.difference', '1.000'));

    $this->actingAs($manager)->put("/receptions/{$cycleId}/{$producerId}/products/{$loaf}/received", ['received' => '5', 'overwrite_manual' => true])->assertRedirect();
    $this->actingAs($manager)->get("/receptions/{$cycleId}/{$producerId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('reception.products.0.received', '5.000')
            ->where('reception.products.0.hasManualAllocations', false)
            ->where('reception.products.0.balanced', true));
});

it('allocates by hand to a household that did not order, taking a snapshot of the product (R16, S39)', function () {
    [$cycleId, $producerId, $loaf, , $households, $manager] = receptionFixture();

    $this->actingAs($manager)->put("/receptions/{$cycleId}/{$producerId}/products/{$loaf}/allocations/{$households['D']}", ['quantity' => '1'])->assertRedirect();

    $this->assertDatabaseHas('order_lines', ['product_id' => $loaf, 'ordered_quantity' => '0.000', 'allocated_quantity' => '1.000', 'allocated_by_hand' => true, 'unit_price_cents' => 350]);
    $this->actingAs($manager)->get("/receptions/{$cycleId}/{$producerId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('reception.products.0.difference', '-1.000')
            ->where('reception.products.0.allocations.2.householdName', 'Household D'));
});

it('refuses an allocation to a deactivated household (R16)', function () {
    [$cycleId, $producerId, $loaf, , $households, $manager] = receptionFixture();
    DB::table('households')->where('id', $households['D'])->update(['deactivated_at' => now()]);

    $this->actingAs($manager)->put("/receptions/{$cycleId}/{$producerId}/products/{$loaf}/allocations/{$households['D']}", ['quantity' => '1'])
        ->assertSessionHasErrors('domain');
});

it('adds a product nobody ordered with its received total and allocates it (R14, R16, S40)', function () {
    [$cycleId, $producerId, , $rye, $households, $manager] = receptionFixture();

    $this->actingAs($manager)->post("/receptions/{$cycleId}/{$producerId}/products", ['product_id' => $rye, 'received' => '3'])->assertRedirect();
    $this->actingAs($manager)->put("/receptions/{$cycleId}/{$producerId}/products/{$rye}/allocations/{$households['A']}", ['quantity' => '2'])->assertRedirect();
    $this->actingAs($manager)->put("/receptions/{$cycleId}/{$producerId}/products/{$rye}/allocations/{$households['B']}", ['quantity' => '1'])->assertRedirect();

    $this->actingAs($manager)->get("/receptions/{$cycleId}/{$producerId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('reception.products.1.name', 'Rye')
            ->where('reception.products.1.nobodyOrdered', true)
            ->where('reception.products.1.received', '3.000')
            ->where('reception.products.1.balanced', true)
            ->has('reception.unorderedOptions', 0));
});

it('keeps the quantities the manager types to valid fields (R15, R16)', function () {
    [$cycleId, $producerId, $loaf, , $households, $manager] = receptionFixture();

    $this->actingAs($manager)->put("/receptions/{$cycleId}/{$producerId}/products/{$loaf}/received", [])->assertSessionHasErrors('received');
    $this->actingAs($manager)->put("/receptions/{$cycleId}/{$producerId}/products/{$loaf}/allocations/{$households['A']}", ['quantity' => '-1'])
        ->assertSessionHasErrors('domain');
    $this->actingAs($manager)->post("/receptions/{$cycleId}/{$producerId}/products", [])->assertSessionHasErrors('product_id');
});

// R17a, R18 — validating, distributing and reopening

it('validates, distributes and settles through the routes, and shows the state (R17a, R18, S27, S47)', function () {
    [$cycleId, $producerId, , , $households, $manager] = receptionFixture();
    $reception = "/receptions/{$cycleId}/{$producerId}";

    $this->actingAs($manager)->post("{$reception}/distribution")->assertSessionHasErrors('domain');

    $this->actingAs($manager)->post("{$reception}/validate")->assertRedirect();
    $this->actingAs($manager)->get($reception)->assertInertia(fn (Assert $page) => $page
        ->where('distribution', ['validated' => true, 'distributed' => false, 'settled' => false, 'closed' => false]));

    $this->actingAs($manager)->post("{$reception}/distribution")->assertRedirect();

    $this->actingAs($manager)->get($reception)->assertInertia(fn (Assert $page) => $page
        ->where('distribution', ['validated' => true, 'distributed' => true, 'settled' => true, 'closed' => false]));
    $this->assertDatabaseHas('cycle_producers', ['cycle_id' => $cycleId, 'producer_id' => $producerId]);
    expect(DB::table('cycles')->where('id', $cycleId)->value('settled_at'))->not->toBeNull()
        ->and(DB::table('baskets')->where('household_id', $households['A'])->whereIn('source_order_id', DB::table('orders')->where('cycle_id', $cycleId)->select('id'))->value('state'))->toBe('draft');
});

it('rejects distributing an unbalanced reception and a changed total clears the validation (R17, R17a, S13, S47)', function () {
    [$cycleId, $producerId, $loaf, , $households, $manager] = receptionFixture();
    $reception = "/receptions/{$cycleId}/{$producerId}";
    $this->actingAs($manager)->put("{$reception}/products/{$loaf}/allocations/{$households['B']}", ['quantity' => '5'])->assertRedirect();
    $this->actingAs($manager)->post("{$reception}/validate")->assertRedirect();

    $this->actingAs($manager)->post("{$reception}/distribution")->assertSessionHasErrors('domain');

    $this->actingAs($manager)->put("{$reception}/products/{$loaf}/received", ['received' => '9', 'overwrite_manual' => true])->assertRedirect();
    $this->actingAs($manager)->get($reception)->assertInertia(fn (Assert $page) => $page->where('distribution.validated', false));
});

it('makes a distributed reception read-only until it is reopened before settlement (R18, S29)', function () {
    [$cycleId, $producerId, $loaf, , , $manager] = receptionFixture();
    $other = ProducerModel::query()->create(['name' => 'Grower', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    DB::table('cycle_producers')->insert(['cycle_id' => $cycleId, 'producer_id' => $other->id, 'participating' => true]);
    $reception = "/receptions/{$cycleId}/{$producerId}";
    $this->actingAs($manager)->post("{$reception}/validate")->assertRedirect();
    $this->actingAs($manager)->post("{$reception}/distribution")->assertRedirect();

    $this->actingAs($manager)->put("{$reception}/products/{$loaf}/received", ['received' => '5'])->assertSessionHasErrors('domain');
    $this->actingAs($manager)->delete("{$reception}/distribution")->assertRedirect();
    $this->actingAs($manager)->put("{$reception}/products/{$loaf}/received", ['received' => '5'])->assertRedirect();

    $this->actingAs($manager)->get($reception)->assertInertia(fn (Assert $page) => $page
        ->where('distribution', ['validated' => false, 'distributed' => false, 'settled' => false, 'closed' => false])
        ->where('reception.products.0.received', '5.000'));
});

it('does not reopen a producer once the cycle is closed (R18)', function () {
    [$cycleId, $producerId, , , , $manager] = receptionFixture();
    $reception = "/receptions/{$cycleId}/{$producerId}";
    $this->actingAs($manager)->post("{$reception}/validate")->assertRedirect();
    $this->actingAs($manager)->post("{$reception}/distribution")->assertRedirect();

    DB::table('cycles')->where('id', $cycleId)->update(['closed_at' => now()]);
    $this->actingAs($manager)->delete("{$reception}/distribution")->assertSessionHasErrors('domain');
});

it('keeps validating, distributing and reopening to managers and refuses a member-producer (R17a, R18)', function () {
    [$cycleId, $producerId] = receptionFixture();
    $reception = "/receptions/{$cycleId}/{$producerId}";
    $member = accountWith(Role::Member, Role::Producer);

    $this->post("{$reception}/validate")->assertRedirect(route('login'));
    $this->actingAs($member)->post("{$reception}/validate")->assertForbidden();
    $this->actingAs($member)->post("{$reception}/distribution")->assertForbidden();
    $this->actingAs($member)->delete("{$reception}/distribution")->assertForbidden();
});

// T06 — Receiver and Finance receive (R6, R7, R8, R14, R15, S7, S11)

function validatedAt(string $cycleId, string $producerId): mixed
{
    return DB::table('cycle_producers')->where('cycle_id', $cycleId)->where('producer_id', $producerId)->value('validated_at');
}

it('lets a Receiver open a reception from the list, set a received total and change an allocation, both saved (S7)', function () {
    [$cycleId, $producerId, $loaf, , $households] = receptionFixture();
    $receiver = accountWith(Role::Receiver);
    $reception = "/receptions/{$cycleId}/{$producerId}";

    $this->actingAs($receiver)->get('/receptions')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Receptions/Index')
        ->where('cycles.0.cycleId', $cycleId)
        ->where('cycles.0.producers.0.producerName', 'Baker')
        ->where('cycles.0.producers.0.state', 'sent'));
    $this->actingAs($receiver)->get($reception)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Receptions/Show')
        ->where('can', ['edit' => true, 'validate' => false, 'distribute' => false, 'reopen' => false])
        ->where('households', fn ($options) => collect($options)->pluck('id')->contains($households['D'])));

    $this->actingAs($receiver)->put("{$reception}/products/{$loaf}/received", ['received' => '5'])->assertRedirect();
    $this->actingAs($receiver)->put("{$reception}/products/{$loaf}/allocations/{$households['D']}", ['quantity' => '1'])->assertRedirect();

    expect(DB::table('received_totals')->where('cycle_id', $cycleId)->where('product_id', $loaf)->value('received_quantity'))->toBe('5.000');
    $this->assertDatabaseHas('order_lines', ['product_id' => $loaf, 'allocated_quantity' => '1.000', 'allocated_by_hand' => true]);
});

it('refuses a Receiver the validate, distribute and reopen routes, which the manager keeps (S7)', function () {
    [$cycleId, $producerId, , , , $manager] = receptionFixture();
    $receiver = accountWith(Role::Receiver);
    $reception = "/receptions/{$cycleId}/{$producerId}";

    $this->actingAs($receiver)->post("{$reception}/validate")->assertForbidden();
    $this->actingAs($receiver)->post("{$reception}/distribution")->assertForbidden();
    $this->actingAs($receiver)->delete("{$reception}/distribution")->assertForbidden();

    $this->actingAs($manager)->post("{$reception}/validate")->assertRedirect();
    $this->actingAs($manager)->get($reception)->assertInertia(fn (Assert $page) => $page
        ->where('can', ['edit' => true, 'validate' => false, 'distribute' => true, 'reopen' => false]));
    $this->actingAs($manager)->post("{$reception}/distribution")->assertRedirect();
    $this->actingAs($manager)->get($reception)->assertInertia(fn (Assert $page) => $page
        ->where('can', ['edit' => false, 'validate' => false, 'distribute' => false, 'reopen' => true]));
});

it('clears the validation when a Receiver changes a received total (R6)', function () {
    [$cycleId, $producerId, $loaf, , , $manager] = receptionFixture();
    $reception = "/receptions/{$cycleId}/{$producerId}";
    $this->actingAs($manager)->post("{$reception}/validate")->assertRedirect();
    expect(validatedAt($cycleId, $producerId))->not->toBeNull();

    $this->actingAs(accountWith(Role::Receiver))->put("{$reception}/products/{$loaf}/received", ['received' => '9'])->assertRedirect();

    expect(validatedAt($cycleId, $producerId))->toBeNull();
});

it('lets Finance receive through the same pages but not settle (R7)', function () {
    [$cycleId, $producerId, $loaf] = receptionFixture();
    $finance = accountWith(Role::Finance);
    $reception = "/receptions/{$cycleId}/{$producerId}";

    $this->actingAs($finance)->get('/receptions')->assertOk();
    $this->actingAs($finance)->get($reception)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('can', ['edit' => true, 'validate' => false, 'distribute' => false, 'reopen' => false]));
    $this->actingAs($finance)->put("{$reception}/products/{$loaf}/received", ['received' => '8'])->assertRedirect();
    $this->actingAs($finance)->post("{$reception}/validate")->assertForbidden();

    expect(DB::table('received_totals')->where('product_id', $loaf)->value('received_quantity'))->toBe('8.000');
});

it('lists only cycles with a sent order, closed ones included, newest pickup first, and reads a distributed reception only (R8)', function () {
    [$cycleId, $producerId, , , , $manager] = receptionFixture();
    $unsent = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $unsent, 'pickup_date' => now()->addDays(60)->toDateString()]);
    DB::table('cycle_producers')->insert(['cycle_id' => $unsent, 'producer_id' => $producerId, 'participating' => true]);
    $older = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $older, 'pickup_date' => now()->subDays(30)->toDateString(), 'closed_at' => now()->subDays(20)]);
    DB::table('cycle_producers')->insert(['cycle_id' => $older, 'producer_id' => $producerId, 'participating' => true, 'sent_at' => now()->subDays(35), 'validated_at' => now()->subDays(33), 'distributed_at' => now()->subDays(32)]);

    $this->actingAs(accountWith(Role::Receiver))->get('/receptions')->assertInertia(fn (Assert $page) => $page
        ->has('cycles', 2)
        ->where('cycles.0.cycleId', $cycleId)
        ->where('cycles.1.cycleId', $older)
        ->where('cycles.1.closed', true)
        ->where('cycles.1.producers.0.state', 'distributed'));
    $this->actingAs($manager)->get("/receptions/{$older}/{$producerId}")->assertInertia(fn (Assert $page) => $page->where('can.edit', false));
});

it('closes the other areas to a Receiver-only account (S11)', function () {
    [$cycleId, $producerId] = receptionFixture();
    $receiver = accountWith(Role::Receiver);

    $this->actingAs($receiver)->get("/manage/cycles/{$cycleId}")->assertForbidden();
    $this->actingAs($receiver)->get('/manage/settings')->assertForbidden();
    $this->actingAs($receiver)->get("/catalogue/producers/{$producerId}/products")->assertForbidden();
    $this->actingAs($receiver)->get('/manage/roles')->assertForbidden();
});

it('redirects the old manager reception URL and refuses it to a Receiver (D4)', function () {
    [$cycleId, $producerId, , , , $manager] = receptionFixture();
    $old = "/manage/cycles/{$cycleId}/receptions/{$producerId}";

    $this->actingAs($manager)->get($old)->assertRedirect("/receptions/{$cycleId}/{$producerId}");
    $this->actingAs(accountWith(Role::Receiver))->get($old)->assertForbidden();
});

it('gives a Receiver the Reception area and its dashboard shortcut (R14, R15)', function () {
    $receiver = accountWith(Role::Receiver);

    $this->actingAs($receiver)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('areaShortcuts.0.role', 'receiver')
        ->where('areaShortcuts.0.href', '/receptions')
        ->where('roleAreas.0.role', 'receiver'));
});

it('sends only the active households, by name, to the reception page (R16)', function () {
    [$cycleId, $producerId, , , $households] = receptionFixture();
    $inactive = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $inactive, 'name' => 'Aardvark Inactive', 'deactivated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    foreach ([accountWith(Role::Receiver), accountWith(Role::Finance)] as $actor) {
        $this->actingAs($actor)->get("/receptions/{$cycleId}/{$producerId}")->assertInertia(fn (Assert $page) => $page
            ->where('households', fn ($options) => collect($options)->pluck('name')->all() === ['Household A', 'Household B', 'Household D'])
            ->where('households', fn ($options) => ! collect($options)->pluck('id')->contains($inactive)));
    }
});
