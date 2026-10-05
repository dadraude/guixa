<?php

declare(strict_types=1);

use App\Models\Household;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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
