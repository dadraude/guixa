<?php

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Shared\Domain\Role;
use Guixa\Shared\Domain\UtcTimestamp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

function bakerProducer(array $overrides = []): ProducerModel
{
    return ProducerModel::query()->create([
        'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00', ...$overrides,
    ]);
}

function producerAccountFor(ProducerModel $producer): User
{
    $user = User::factory()->create(['producer_id' => $producer->id]);
    DB::table('account_roles')->insert(['user_id' => $user->id, 'role' => Role::Producer->value]);

    return $user;
}

it('keeps the producers admin pages to managers', function (string $method, string $uri) {
    $producer = bakerProducer();
    $uri = str_replace('{id}', $producer->id, $uri);

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->{$method}($uri)->assertForbidden();
})->with([
    ['get', '/manage/producers'],
    ['post', '/manage/producers'],
    ['post', '/manage/producers/{id}/account'],
]);

it('lists the producers with their default cutoff (R36)', function () {
    bakerProducer();

    $this->actingAs(accountWith(Role::Manager))->get('/manage/producers')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Producers/Index')
            ->has('producers', 1)
            ->where('producers.0.name', 'Baker')
            ->where('producers.0.active', true)
            ->where('producers.0.cutoffDaysBefore', 3)
            ->where('producers.0.cutoffTime', '20:00'));
});

it('shows a producer with no account as having none, so the invite form applies (R36)', function () {
    bakerProducer();

    $this->actingAs(accountWith(Role::Manager))->get('/manage/producers')
        ->assertInertia(fn (Assert $page) => $page
            ->where('producers.0.accountEmail', null)
            ->where('producers.0.accountInvitationPending', false));
});

it('shows a producer that already has an account with its email and invitation state (R36)', function () {
    Mail::fake();
    $producer = bakerProducer();
    $manager = accountWith(Role::Manager);
    $this->actingAs($manager)->post("/manage/producers/{$producer->id}/account", ['name' => 'Baker', 'email' => 'baker@example.org']);

    $this->actingAs($manager)->get('/manage/producers')
        ->assertInertia(fn (Assert $page) => $page
            ->where('producers.0.accountEmail', 'baker@example.org')
            ->where('producers.0.accountInvitationPending', true));

    DB::table('users')->where('email', 'baker@example.org')->update(['activated_at' => UtcTimestamp::toStorage(now())]);

    $this->actingAs($manager)->get('/manage/producers')
        ->assertInertia(fn (Assert $page) => $page
            ->where('producers.0.accountEmail', 'baker@example.org')
            ->where('producers.0.accountInvitationPending', false));
});

it('creates a producer and redirects to the list (R36)', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/producers', ['name' => 'Baker', 'cutoff_days_before' => 3, 'cutoff_time' => '20:00'])
        ->assertRedirect('/manage/producers');

    $producer = ProducerModel::query()->firstOrFail();
    expect($producer->name)->toBe('Baker')
        ->and($producer->cutoff_days_before)->toBe(3)
        ->and(substr($producer->cutoff_time, 0, 5))->toBe('20:00');
});

it('shows the domain error when the cutoff is out of range', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->from('/manage/producers')
        ->post('/manage/producers', ['name' => 'Baker', 'cutoff_days_before' => 9, 'cutoff_time' => '20:00'])
        ->assertRedirect('/manage/producers')
        ->assertSessionHasErrors('domain');

    expect(ProducerModel::query()->count())->toBe(0);
});

it('rejects a missing name before reaching the use case', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->from('/manage/producers')
        ->post('/manage/producers', ['name' => '', 'cutoff_days_before' => 3, 'cutoff_time' => '20:00'])
        ->assertSessionHasErrors('name');
});

it('invites the producer account and emails the invitation (R36, S57)', function () {
    Mail::fake();
    $producer = bakerProducer();

    $this->actingAs(accountWith(Role::Manager))
        ->post("/manage/producers/{$producer->id}/account", ['name' => 'Baker', 'email' => 'baker@example.org'])
        ->assertRedirect();

    $user = User::query()->where('email', 'baker@example.org')->firstOrFail();
    expect($user->producer_id)->toBe($producer->id)
        ->and(DB::table('account_roles')->where('user_id', $user->id)->value('role'))->toBe('producer')
        ->and(DB::table('invitations')->where('account_id', $user->id)->exists())->toBeTrue();
});

it('refuses a second account for the same producer with a domain error (S57)', function () {
    Mail::fake();
    $producer = bakerProducer();
    $manager = accountWith(Role::Manager);
    $this->actingAs($manager)->post("/manage/producers/{$producer->id}/account", ['name' => 'Baker', 'email' => 'baker@example.org']);

    $this->actingAs($manager)
        ->from('/manage/producers')
        ->post("/manage/producers/{$producer->id}/account", ['name' => 'Baker 2', 'email' => 'other@example.org'])
        ->assertSessionHasErrors('domain');

    expect(User::query()->where('producer_id', $producer->id)->count())->toBe(1);
});

it('answers 404 to a producer id that is not a UUID', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/producers/not-a-uuid/account', ['name' => 'Baker', 'email' => 'baker@example.org'])
        ->assertNotFound();
});

// R5 — the producer's own cutoff page

it('keeps the cutoff page to producers', function (string $method) {
    $this->{$method}('/producer/cutoff')->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Manager))->{$method}('/producer/cutoff')->assertForbidden();
})->with(['get', 'put']);

it('shows a producer its default cutoff (R5)', function () {
    $producer = bakerProducer();

    $this->actingAs(producerAccountFor($producer))->get('/producer/cutoff')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Producer/Cutoff')
            ->where('cutoff.daysBefore', 3)
            ->where('cutoff.time', '20:00'));
});

it('lets a producer change its default cutoff (R5)', function () {
    $producer = bakerProducer();

    $this->actingAs(producerAccountFor($producer))
        ->put('/producer/cutoff', ['days_before' => 1, 'time' => '02:30'])
        ->assertRedirect();

    $producer->refresh();
    expect($producer->cutoff_days_before)->toBe(1)
        ->and(substr($producer->cutoff_time, 0, 5))->toBe('02:30');
});

it('shows the domain error when the new cutoff is out of range (R5)', function () {
    $producer = bakerProducer();

    $this->actingAs(producerAccountFor($producer))
        ->from('/producer/cutoff')
        ->put('/producer/cutoff', ['days_before' => 7, 'time' => '20:00'])
        ->assertRedirect('/producer/cutoff')
        ->assertSessionHasErrors('domain');

    expect($producer->refresh()->cutoff_days_before)->toBe(3);
});

it('refuses a producer role not linked to a producer', function () {
    $this->actingAs(accountWith(Role::Producer))->get('/producer/cutoff')->assertForbidden();
});
