<?php

use App\Models\User;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

/** A producer in a future cycle; $sent moves its order to sent. Returns [cycle id, producer id]. */
function producerInCycle(string $name, ?string $cycle = null, bool $sent = false): array
{
    $producer = (string) Str::uuid7();
    DB::table('producers')->insert(['id' => $producer, 'name' => $name, 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $cycle ??= (string) Str::uuid7();
    if (! DB::table('cycles')->where('id', $cycle)->exists()) {
        DB::table('cycles')->insert(['id' => $cycle, 'pickup_date' => now()->addDays(30)->toDateString()]);
    }
    DB::table('cycle_producers')->insert([
        'cycle_id' => $cycle,
        'producer_id' => $producer,
        'participating' => true,
        'closes_at' => $sent ? now()->subHour() : now()->addDays(10)->setTime(20, 0),
        'sent_at' => $sent ? now() : null,
    ]);

    return [$cycle, $producer];
}

it('shows a member the open order link and cutoffs below an empty attention list, and no household data (R11, S3)', function () {
    [$cycle] = producerInCycle('Baker');
    $member = accountWith(Role::Member);

    $this->actingAs($member)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('attention', 0)
        ->has('opportunities', 1)
        ->where('opportunities.0.kind', 'member_order')
        ->where('opportunities.0.href', "/cycles/{$cycle}/order")
        ->where('opportunities.0.cutoffs.0.producerName', 'Baker'));
});

it('shows only the new household context after a member moves, and nothing once unlinked (S2, R1)', function () {
    producerInCycle('Baker');
    $member = accountWith(Role::Member);
    linkToNewHousehold($member);

    $this->actingAs($member)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('opportunities', 1));

    DB::table('users')->where('id', $member->id)->update(['household_id' => null]);
    app('auth')->forgetGuards();
    $this->actingAs($member->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('opportunities', 0));
});

it('shows a producer only its own sent summary, with no household or other producer data (S2, S10)', function () {
    [$cycle, $own] = producerInCycle('Own', sent: true);
    producerInCycle('Other', $cycle, sent: true);
    $account = User::factory()->create(['producer_id' => $own]);
    DB::table('account_roles')->insert(['user_id' => $account->id, 'role' => Role::Producer->value]);

    $response = $this->actingAs($account)->get('/dashboard');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('attention', 0)
        ->has('opportunities', 1)
        ->where('opportunities.0.kind', 'producer_summary')
        ->where('opportunities.0.href', "/producer/cycles/{$cycle}")
        ->where('opportunities.0.cutoffs', []));
    expect(json_encode($response->viewData('page')['props']['opportunities']))->not->toContain('Other');
});

it('takes the producer opportunity away when the account loses its producer link or its Producer role (R1, R11)', function () {
    [, $own] = producerInCycle('Own', sent: true);
    $account = User::factory()->create(['producer_id' => $own]);
    DB::table('account_roles')->insert(['user_id' => $account->id, 'role' => Role::Producer->value]);
    $count = function () use ($account) {
        app('auth')->forgetGuards();

        return count($this->actingAs($account->fresh())->get('/dashboard')->viewData('page')['props']['opportunities']);
    };

    expect($count())->toBe(1);

    DB::table('users')->where('id', $account->id)->update(['producer_id' => null]);
    expect($count())->toBe(0);

    DB::table('users')->where('id', $account->id)->update(['producer_id' => $own]);
    expect($count())->toBe(1);

    DB::table('account_roles')->where('user_id', $account->id)->delete();
    expect($count())->toBe(0);
});

it('shows Catalogue the maintenance link even with an inactive product, and takes it away with the role (S10, R1)', function () {
    [, $producer] = producerInCycle('Baker');
    DB::table('products')->insert(['id' => (string) Str::uuid7(), 'producer_id' => $producer, 'name' => 'Retired', 'unit' => 'unit', 'price_cents' => 300, 'variable' => false, 'active' => false]);
    $account = accountWith(Role::Catalogue);

    $this->actingAs($account)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->has('attention', 0)
        ->has('opportunities', 1)
        ->where('opportunities.0.kind', 'catalogue_maintenance')
        ->where('opportunities.0.href', '/catalogue/producers'));

    DB::table('account_roles')->where('user_id', $account->id)->delete();
    app('auth')->forgetGuards();
    $this->actingAs($account->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('opportunities', 0));
});
