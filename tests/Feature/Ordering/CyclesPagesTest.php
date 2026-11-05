<?php

use Carbon\CarbonImmutable;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FrozenClock;

afterEach(fn () => CarbonImmutable::setTestNow());

/** The first date at least a week ahead that falls on the given ISO weekday, in the cooperative's timezone. */
function upcoming(int $isoWeekday = 6): CarbonImmutable
{
    $date = CarbonImmutable::now(config('guixa.timezone'))->startOfDay()->addDays(7);

    while ($date->dayOfWeekIso !== $isoWeekday) {
        $date = $date->addDay();
    }

    return $date;
}

function pickupOnSaturdays(): void
{
    DB::table('cooperative_settings')->where('id', 1)->update(['pickup_weekday' => 6]);
}

function cyclesBaker(array $overrides = []): ProducerModel
{
    return ProducerModel::query()->create([
        'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00', ...$overrides,
    ]);
}

it('keeps the cycle and settings pages to managers', function (string $method, string $uri) {
    $baker = cyclesBaker();
    $cycleId = (string) fake()->uuid();
    DB::table('cycles')->insert(['id' => $cycleId, 'pickup_date' => upcoming()->toDateString()]);
    $uri = str_replace(['{cycle}', '{producer}'], [$cycleId, $baker->id], $uri);

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->{$method}($uri)->assertForbidden();
})->with([
    ['get', '/manage/cycles'],
    ['post', '/manage/cycles'],
    ['post', '/manage/cycles/{cycle}/producers/{producer}'],
    ['get', '/manage/settings'],
    ['put', '/manage/settings/pickup-weekday'],
]);

it('shows no pickup weekday until one is set, and lets a manager change it (R4c)', function () {
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->get('/manage/settings')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Settings')
            ->where('pickupWeekday', null));

    $this->actingAs($manager)->put('/manage/settings/pickup-weekday', ['pickup_weekday' => 5])
        ->assertRedirect();

    expect(DB::table('cooperative_settings')->value('pickup_weekday'))->toBe(5);
});

it('rejects a pickup weekday outside Monday to Sunday (R4c)', function (mixed $weekday) {
    $this->actingAs(accountWith(Role::Manager))
        ->put('/manage/settings/pickup-weekday', ['pickup_weekday' => $weekday])
        ->assertSessionHasErrors('pickup_weekday');

    expect(DB::table('cooperative_settings')->value('pickup_weekday'))->toBeNull();
})->with([0, 8, 'saturday', null]);

it('opens a cycle with its participants and shows its cutoffs (R4, R4a, R5, S6)', function () {
    CarbonImmutable::setTestNow('2026-10-28T22:38:00+00:00');
    $this->app->instance(Clock::class, new FrozenClock('2026-10-28T22:38:00+00:00', config('guixa.timezone')));
    pickupOnSaturdays();
    $baker = cyclesBaker();
    $date = upcoming();

    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/cycles', ['pickup_date' => $date->toDateString(), 'producer_ids' => [$baker->id]])
        ->assertRedirect('/manage/cycles');

    $cutoff = $date->subDays(3)->setTime(20, 0);
    $this->actingAs(accountWith(Role::Manager))->get('/manage/cycles')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Cycles/Index')
            ->has('cycles', 1)
            ->where('cycles.0.pickupDate', $date->toDateString())
            ->has('cycles.0.participants', 1)
            ->where('cycles.0.participants.0.producerId', $baker->id)
            ->where('cycles.0.participants.0.producerName', 'Baker')
            ->where('cycles.0.participants.0.participating', true)
            ->where('cycles.0.participants.0.cutoff', $cutoff->format('c')));
});

it('opens a cycle with no participants (R4a)', function () {
    pickupOnSaturdays();
    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/cycles', ['pickup_date' => upcoming()->toDateString()])
        ->assertRedirect('/manage/cycles');

    expect(DB::table('cycles')->count())->toBe(1)
        ->and(DB::table('cycle_producers')->count())->toBe(0);
});

it('offers only active producers to take part in a cycle (R4a, S50)', function () {
    pickupOnSaturdays();
    cyclesBaker();
    cyclesBaker(['name' => 'Retired', 'active' => false]);

    $this->actingAs(accountWith(Role::Manager))->get('/manage/cycles')
        ->assertInertia(fn (Assert $page) => $page
            ->has('producers', 1)
            ->where('producers.0.name', 'Baker')
            ->where('pickupWeekday', 6));
});

it('shows the domain error for a date off the pickup weekday (S37, R4c)', function () {
    pickupOnSaturdays();
    $friday = upcoming(5);

    $this->actingAs(accountWith(Role::Manager))
        ->from('/manage/cycles')
        ->post('/manage/cycles', ['pickup_date' => $friday->toDateString()])
        ->assertRedirect('/manage/cycles')
        ->assertSessionHasErrors('domain');

    expect(DB::table('cycles')->count())->toBe(0);
});

it('shows the domain error when the date already has a cycle (R4)', function () {
    pickupOnSaturdays();
    $manager = accountWith(Role::Manager);
    $date = upcoming()->toDateString();
    $this->actingAs($manager)->post('/manage/cycles', ['pickup_date' => $date]);

    $this->actingAs($manager)
        ->from('/manage/cycles')
        ->post('/manage/cycles', ['pickup_date' => $date])
        ->assertSessionHasErrors('domain');

    expect(DB::table('cycles')->count())->toBe(1);
});

it('validates the form before the use case', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/cycles', ['pickup_date' => '', 'producer_ids' => 'not-a-list'])
        ->assertSessionHasErrors(['pickup_date', 'producer_ids']);
});

it('makes a producer participate in an existing cycle (R4a, S51)', function () {
    pickupOnSaturdays();
    $manager = accountWith(Role::Manager);
    $baker = cyclesBaker();
    $this->actingAs($manager)->post('/manage/cycles', ['pickup_date' => upcoming()->toDateString()]);
    $cycleId = DB::table('cycles')->value('id');

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/producers/{$baker->id}")
        ->assertRedirect();

    expect(DB::table('cycle_producers')->where('cycle_id', $cycleId)->where('producer_id', $baker->id)->exists())->toBeTrue();
});

it('answers with the domain error when the cycle is unknown', function () {
    $baker = cyclesBaker();

    $this->actingAs(accountWith(Role::Manager))
        ->from('/manage/cycles')
        ->post('/manage/cycles/'.fake()->uuid()."/producers/{$baker->id}")
        ->assertSessionHasErrors('domain');
});

it('opens a cycle with a permanent weekday, which becomes the cooperative\'s (R4c)', function () {
    $friday = upcoming(5);

    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/cycles', ['pickup_date' => $friday->toDateString(), 'pickup_weekday' => 5, 'permanent' => '1'])
        ->assertRedirect('/manage/cycles');

    expect(DB::table('cycles')->count())->toBe(1)
        ->and(DB::table('cooperative_settings')->value('pickup_weekday'))->toBe(5);
});

it('opens a cycle with a provisional weekday and leaves the cooperative without one (R4c)', function () {
    $friday = upcoming(5);

    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/cycles', ['pickup_date' => $friday->toDateString(), 'pickup_weekday' => 5, 'permanent' => '0'])
        ->assertRedirect('/manage/cycles');

    expect(DB::table('cycles')->count())->toBe(1)
        ->and(DB::table('cooperative_settings')->value('pickup_weekday'))->toBeNull();
});

it('asks for the weekday when the cooperative has none (R4c)', function () {
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->get('/manage/cycles')
        ->assertInertia(fn (Assert $page) => $page->where('pickupWeekday', null));

    $this->actingAs($manager)
        ->from('/manage/cycles')
        ->post('/manage/cycles', ['pickup_date' => upcoming()->toDateString()])
        ->assertRedirect('/manage/cycles')
        ->assertSessionHasErrors('domain');

    expect(DB::table('cycles')->count())->toBe(0);
});

it('requires the permanent or provisional choice when a weekday is given (R4c)', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/cycles', ['pickup_date' => upcoming(5)->toDateString(), 'pickup_weekday' => 5])
        ->assertSessionHasErrors('permanent');

    expect(DB::table('cycles')->count())->toBe(0);
});

it('refuses a weekday given when the cooperative already has one (R4c)', function () {
    pickupOnSaturdays();

    $this->actingAs(accountWith(Role::Manager))
        ->from('/manage/cycles')
        ->post('/manage/cycles', ['pickup_date' => upcoming(5)->toDateString(), 'pickup_weekday' => 5, 'permanent' => '1'])
        ->assertSessionHasErrors('domain');

    expect(DB::table('cycles')->count())->toBe(0)
        ->and(DB::table('cooperative_settings')->value('pickup_weekday'))->toBe(6);
});

function openCycleWithBaker(): array
{
    pickupOnSaturdays();
    $baker = cyclesBaker();
    $date = upcoming();
    test()->actingAs(accountWith(Role::Manager))
        ->post('/manage/cycles', ['pickup_date' => $date->toDateString(), 'producer_ids' => [$baker->id]]);

    return [DB::table('cycles')->value('id'), $baker, $date];
}

it('keeps the cutoff routes to managers (R6)', function (string $method, string $suffix) {
    [$cycleId, $baker] = openCycleWithBaker();
    $uri = "/manage/cycles/{$cycleId}/producers/{$baker->id}/cutoff{$suffix}";

    auth()->logout();
    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->{$method}($uri)->assertForbidden();
})->with([
    ['put', ''],
    ['post', '/close'],
    ['delete', ''],
]);

it('moves a producer\'s cutoff and shows whether it is open (R6, R6a, S6)', function () {
    [$cycleId, $baker, $date] = openCycleWithBaker();
    $manager = accountWith(Role::Manager);
    $moved = $date->subDays(2)->setTime(12, 0);

    $this->actingAs($manager)
        ->put("/manage/cycles/{$cycleId}/producers/{$baker->id}/cutoff", ['cutoff' => $moved->format('Y-m-d\TH:i')])
        ->assertRedirect();

    $this->actingAs($manager)->get('/manage/cycles')
        ->assertInertia(fn (Assert $page) => $page
            ->where('cycles.0.participants.0.cutoff', $moved->format('c'))
            ->where('cycles.0.participants.0.open', true));
});

it('shows the domain error for a cutoff outside the allowed window (R6, S6)', function () {
    [$cycleId, $baker, $date] = openCycleWithBaker();

    $this->actingAs(accountWith(Role::Manager))
        ->from('/manage/cycles')
        ->put("/manage/cycles/{$cycleId}/producers/{$baker->id}/cutoff", ['cutoff' => $date->setTime(8, 0)->format('Y-m-d\TH:i')])
        ->assertRedirect('/manage/cycles')
        ->assertSessionHasErrors('domain');
});

it('validates that a cutoff is given (R6)', function () {
    [$cycleId, $baker] = openCycleWithBaker();

    $this->actingAs(accountWith(Role::Manager))
        ->put("/manage/cycles/{$cycleId}/producers/{$baker->id}/cutoff", ['cutoff' => ''])
        ->assertSessionHasErrors('cutoff');
});

it('closes a producer now (R6, S35)', function () {
    [$cycleId, $baker] = openCycleWithBaker();
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/producers/{$baker->id}/cutoff/close")->assertRedirect();

    $this->actingAs($manager)->get('/manage/cycles')
        ->assertInertia(fn (Assert $page) => $page->where('cycles.0.participants.0.open', false));
});

it('clears a producer\'s cutoff (R6, S35)', function () {
    [$cycleId, $baker] = openCycleWithBaker();
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->delete("/manage/cycles/{$cycleId}/producers/{$baker->id}/cutoff")->assertRedirect();

    $this->actingAs($manager)->get('/manage/cycles')
        ->assertInertia(fn (Assert $page) => $page
            ->where('cycles.0.participants.0.cutoff', null)
            ->where('cycles.0.participants.0.open', true));
});

it('answers with the domain error when the producer is not in the cycle (R6)', function () {
    [$cycleId] = openCycleWithBaker();
    $other = cyclesBaker(['name' => 'Other']);

    $this->actingAs(accountWith(Role::Manager))
        ->from('/manage/cycles')
        ->delete("/manage/cycles/{$cycleId}/producers/{$other->id}/cutoff")
        ->assertSessionHasErrors('domain');
});

it('keeps the cancel route to managers (R4b)', function () {
    [$cycleId] = openCycleWithBaker();

    auth()->logout();
    $this->post("/manage/cycles/{$cycleId}/cancel")->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->post("/manage/cycles/{$cycleId}/cancel")->assertForbidden();
});

it('cancels a cycle, retains its orders as cancelled and shows it as cancelled (R4b, S37)', function () {
    [$cycleId] = openCycleWithBaker();
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/cancel")->assertRedirect();

    expect(DB::table('cycles')->where('id', $cycleId)->value('cancelled_at'))->not->toBeNull();
    $this->actingAs($manager)->get('/manage/cycles')
        ->assertInertia(fn (Assert $page) => $page->where('cycles.0.cancelled', true));
});

it('answers with the domain error when the cycle is already cancelled (R4b)', function () {
    [$cycleId] = openCycleWithBaker();
    $manager = accountWith(Role::Manager);
    $this->actingAs($manager)->post("/manage/cycles/{$cycleId}/cancel");

    $this->actingAs($manager)
        ->from('/manage/cycles')
        ->post("/manage/cycles/{$cycleId}/cancel")
        ->assertRedirect('/manage/cycles')
        ->assertSessionHasErrors('domain');
});
