<?php

use App\Models\User;
use Guixa\Ordering\Application\SetOrderLine;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

/** A closed, ordered baker order with a producer account: what 001 R12a lets a manager send. Returns [cycle id, producer id]. */
function closedBakerOrderWithAccount(): array
{
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household', 'created_at' => now(), 'updated_at' => now()]);
    $baker = (string) Str::uuid7();
    DB::table('producers')->insert(['id' => $baker, 'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $loaf = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $loaf, 'producer_id' => $baker, 'name' => 'Loaf', 'unit' => 'unit', 'price_cents' => 300, 'variable' => false, 'active' => true]);
    $cycle = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $cycle, 'pickup_date' => now()->addDays(30)->toDateString()]);
    DB::table('cycle_producers')->insert(['cycle_id' => $cycle, 'producer_id' => $baker, 'participating' => true, 'closes_at' => null]);
    app(SetOrderLine::class)(Actor::memberOf(User::factory()->create()->id, $household), $cycle, $loaf, '2');
    User::factory()->create(['producer_id' => $baker, 'email' => 'baker@example.test']);
    DB::table('cycle_producers')->where('cycle_id', $cycle)->update(['closes_at' => now()->subMinute()->toIso8601String()]);

    return [$cycle, $baker];
}

it('renders the eligible send as a group linking to the existing cycle screen (S8, R9)', function () {
    [$cycle] = closedBakerOrderWithAccount();

    $this->actingAs(accountWith(Role::Manager))->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('attention', 1)
        ->where('attention.0.action', 'send_order')
        ->where('attention.0.cycleId', $cycle)
        ->where('attention.0.count', 1)
        ->where('attention.0.producerName', 'Baker')
        ->where('attention.0.href', "/manage/cycles/{$cycle}"));
});

it('shows Finance, Receiver and a member no send group (R9)', function () {
    closedBakerOrderWithAccount();

    foreach ([[Role::Finance], [Role::Receiver], [Role::Member]] as $roles) {
        app('auth')->forgetGuards();
        $this->actingAs(accountWith(...$roles))->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('attention', fn ($attention) => collect($attention)->where('action', 'send_order')->isEmpty()));
    }
});

it('changes nothing by reading the dashboard and offers no write route from it (R10)', function () {
    closedBakerOrderWithAccount();
    $tables = fn () => array_map(fn (string $table) => DB::table($table)->get()->toArray(), ['cycles', 'cycle_producers', 'orders', 'order_lines']);
    $before = $tables();

    $this->actingAs(accountWith(Role::Manager))->get('/dashboard')->assertOk();

    expect($tables())->toEqual($before);
});

it('reads manual sends with a bounded query count as eligible producers are added (performance)', function () {
    [$cycle] = closedBakerOrderWithAccount();
    $manager = accountWith(Role::Manager);
    $queries = function () use ($manager) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($manager)->get('/dashboard')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $queries();
    $one = $queries();

    $extra = 4;
    for ($i = 0; $i < $extra; $i++) {
        $producer = (string) Str::uuid7();
        DB::table('producers')->insert(['id' => $producer, 'name' => "Extra {$i}", 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        $product = (string) Str::uuid7();
        DB::table('products')->insert(['id' => $product, 'producer_id' => $producer, 'name' => "Item {$i}", 'unit' => 'unit', 'price_cents' => 300, 'variable' => false, 'active' => true]);
        DB::table('cycle_producers')->insert(['cycle_id' => $cycle, 'producer_id' => $producer, 'participating' => true, 'closes_at' => null]);
        $household = DB::table('households')->value('id');
        app(SetOrderLine::class)(Actor::memberOf(User::factory()->create()->id, $household), $cycle, $product, '1');
        DB::table('cycle_producers')->where('producer_id', $producer)->update(['closes_at' => now()->subMinute()->toIso8601String()]);
        User::factory()->create(['producer_id' => $producer, 'email' => "extra{$i}@example.test"]);
    }

    // Per eligible producer: the account lookup (user and roles, 4) plus the product and producer reads of SendableTotals (4), which /manage/cycles pays too.
    expect($queries() - $one)->toBeLessThanOrEqual($extra * 8);
});
