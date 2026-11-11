<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

/** A baker's order that two households placed and that has been sent; returns [cycle id, producer id]. */
function sentBakerOrder(): array
{
    [$cycleId, $baker, $loaf] = cycleWithBaker();
    foreach (['A' => '4', 'B' => '6'] as $name => $quantity) {
        $household = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $household, 'name' => "Household {$name}", 'created_at' => now(), 'updated_at' => now()]);
        test()->actingAs(memberOf($household, "Member {$name}"))->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => $quantity])->assertRedirect();
        app('auth')->forgetGuards();
    }
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['closes_at' => now()->subHour(), 'sent_at' => now()]);

    return [$cycleId, $baker->id];
}

/** Every table a reception read could write: the reception's own rows, the received totals and the orders it loads. */
function receptionTables(): array
{
    return array_map(
        fn (string $table) => DB::table($table)->get()->toArray(),
        ['received_totals', 'cycle_producers', 'cycles', 'orders', 'order_lines'],
    );
}

it('renders an unopened sent order as a check group linking to its reception, writing no reception row (S5)', function () {
    [$cycleId, $producerId] = sentBakerOrder();
    $before = receptionTables();

    $this->actingAs(accountWith(Role::Receiver))->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('attention', 1)
        ->where('attention.0.action', 'check_delivery_and_allocations')
        ->where('attention.0.cycleId', $cycleId)
        ->where('attention.0.count', 1)
        ->where('attention.0.producerName', 'Baker')
        ->where('attention.0.allocationDifferenceCount', 0)
        ->where('attention.0.href', "/receptions/{$cycleId}/{$producerId}"));

    expect(DB::table('received_totals')->count())->toBe(0)
        ->and(receptionTables())->toEqual($before);
});

it('links a group of several receptions to the reception list', function () {
    [$cycleId] = sentBakerOrder();
    $grower = ProducerModel::query()->create(['name' => 'Grower', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    DB::table('cycle_producers')->insert(['cycle_id' => $cycleId, 'producer_id' => $grower->id, 'participating' => true, 'closes_at' => now()->subHour(), 'sent_at' => now()]);

    $this->actingAs(accountWith(Role::Finance))->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->has('attention', 1)
        ->where('attention.0.count', 2)
        ->where('attention.0.producerName', null)
        ->where('attention.0.href', '/receptions'));
});

it('shows Finance a validated balanced reception to distribute, which a Receiver does not see (S6, R10)', function () {
    [$cycleId, $producerId] = sentBakerOrder();
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['validated_at' => now()]);

    $this->actingAs(accountWith(Role::Finance))->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('attention.0.action', 'mark_distributed')
        ->where('attention.0.href', "/receptions/{$cycleId}/{$producerId}"));
    app('auth')->forgetGuards();
    $this->actingAs(accountWith(Role::Receiver))->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('attention', 0));
});

it('shows reception work to neither a member, a producer nor Catalogue, and drops a distributed reception', function () {
    [$cycleId] = sentBakerOrder();

    foreach ([[Role::Member], [Role::Producer], [Role::Catalogue]] as $roles) {
        $this->actingAs(accountWith(...$roles))->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('attention', 0));
        app('auth')->forgetGuards();
    }

    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['validated_at' => now(), 'distributed_at' => now()]);
    $this->actingAs(accountWith(Role::Manager))->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('attention', 0));
});

it('leaves the destination commands to their own gates: a Receiver still cannot validate or distribute (R10)', function () {
    [$cycleId, $producerId] = sentBakerOrder();
    $receiver = accountWith(Role::Receiver);
    $before = receptionTables();

    $this->actingAs($receiver)->get('/dashboard')->assertOk();
    $this->post("/receptions/{$cycleId}/{$producerId}/validate")->assertForbidden();
    $this->post("/receptions/{$cycleId}/{$producerId}/distribution")->assertForbidden();

    expect(receptionTables())->toEqual($before);
});

it('is a read-only route: the dashboard accepts only GET and HEAD', function () {
    $methods = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => $route->uri() === 'dashboard')->flatMap(fn ($route) => $route->methods())->unique()->sort()->values()->all();

    expect($methods)->toBe(['GET', 'HEAD']);
});
