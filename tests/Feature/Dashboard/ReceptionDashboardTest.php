<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FrozenClock;

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

it('drops the reception group on the next read once the Receiver role is removed, and the stale reception link refuses (S12, R1)', function () {
    [$cycleId, $producerId] = sentBakerOrder();
    $receiver = accountWith(Role::Receiver);
    $link = "/receptions/{$cycleId}/{$producerId}";

    $this->actingAs($receiver)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->has('attention', 1)->where('attention.0.href', $link));
    $this->get($link)->assertOk();

    DB::table('account_roles')->where('user_id', $receiver->id)->where('role', Role::Receiver->value)->delete();
    app('auth')->forgetGuards();

    $this->actingAs($receiver->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->has('attention', 0)->where('can.showNoContext', true));
    $this->get($link)->assertForbidden();
});

it('shows a member who is also Finance and Receiver one reception group, whose link opens the reception (S1, R2)', function () {
    [$cycleId, $producerId] = sentBakerOrder();
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household C', 'created_at' => now(), 'updated_at' => now()]);
    $combined = memberOf($household);
    DB::table('account_roles')->insert([
        ['user_id' => $combined->id, 'role' => Role::Finance->value],
        ['user_id' => $combined->id, 'role' => Role::Receiver->value],
    ]);
    $link = "/receptions/{$cycleId}/{$producerId}";

    $this->actingAs($combined)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->has('attention', 1)
        ->where('attention.0.action', 'check_delivery_and_allocations')
        ->where('attention.0.href', $link));
    $this->get($link)->assertOk();
});

it('keeps a reception distributed elsewhere until the next read, opens its current state, then drops it with a later read time (S11, R15)', function () {
    [$cycleId, $producerId] = sentBakerOrder();
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['validated_at' => now()]);
    $finance = accountWith(Role::Finance);
    $link = "/receptions/{$cycleId}/{$producerId}";
    $clock = new FrozenClock(now()->toDateTimeString(), config('guixa.timezone'));
    $this->app->instance(Clock::class, $clock);

    $firstRead = $clock->now()->format(DATE_ATOM);
    $this->actingAs($finance)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->has('attention', 1)->where('attention.0.href', $link)->where('lastUpdatedAt', $firstRead));

    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['distributed_at' => now()]);
    $this->get($link)->assertOk();
    $this->post("/receptions/{$cycleId}/{$producerId}/distribution")->assertRedirect()->assertSessionHasErrors();

    $clock->travelTo($clock->now()->modify('+5 minutes')->format('Y-m-d H:i:s'));
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->has('attention', 0)
        ->where('lastUpdatedAt', $clock->now()->format(DATE_ATOM)));
    expect($clock->now()->format(DATE_ATOM))->not->toBe($firstRead);
});
