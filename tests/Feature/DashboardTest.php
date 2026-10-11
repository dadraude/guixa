<?php

declare(strict_types=1);

use App\Models\Household;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the sign-in page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('signed-in users can visit the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk();
});

test('a member sees their own household and its members, and no other household', function () {
    $own = Household::factory()->create(['name' => 'Cal Pere']);
    $other = Household::factory()->create(['name' => 'Ca la Maria']);
    $anna = User::factory()->member($own)->create(['name' => 'Anna Puig']);
    User::factory()->member($own)->create(['name' => 'Pere Puig']);
    User::factory()->member($other)->create(['name' => 'Maria Soler']);

    $this->actingAs($anna)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('household.number', $own->fresh()->number)
            ->where('household.name', 'Cal Pere')
            ->has('household.members', 2)
            ->where('household.members.0.name', 'Anna Puig')
            ->where('household.members.1.name', 'Pere Puig'))
        ->assertDontSee('Ca la Maria')
        ->assertDontSee('Maria Soler');
});

test('a user without a member profile has no household', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('household', null));
});

test('the housemates are ordered by name, accents and case ignored', function () {
    $household = Household::factory()->create();
    $zoe = User::factory()->member($household)->create(['name' => 'Zoe Mas']);
    User::factory()->member($household)->create(['name' => 'emma Puig']);
    User::factory()->member($household)->create(['name' => 'Àlex Roca']);

    $this->actingAs($zoe)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('household.members.0.name', 'Àlex Roca')
            ->where('household.members.1.name', 'emma Puig')
            ->where('household.members.2.name', 'Zoe Mas'));
});

test('the shared user props carry no member or administrator relation', function (User $user) {
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('auth.user.member')
            ->missing('auth.user.administrator')
            ->has('auth.user', fn (Assert $shared) => $shared
                ->hasAll(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'])
                ->etc()));
})->with([
    'member' => fn () => User::factory()->member(Household::factory()->create())->create(),
    'owner who is also an administrator' => fn () => User::factory()->owner()->create(),
    'administrator' => fn () => User::factory()->administrator()->create(),
]);
