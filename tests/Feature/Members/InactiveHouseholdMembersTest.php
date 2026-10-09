<?php

declare(strict_types=1);

use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function inactiveHouseholdUser(string $name = 'Anna Puig'): User
{
    return User::factory()->member(Household::factory()->inactive()->create())->create(['name' => $name]);
}

function inactiveHouseholdMembershipActor(): User
{
    $user = User::factory()->member(Household::factory()->create())->create();
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($user->member->fresh());

    return $user->fresh();
}

test('an active member of an inactive household with no commission is sent to the waiting screen', function (string $route) {
    $this->actingAs(inactiveHouseholdUser())
        ->get(route($route))
        ->assertRedirect(route('waiting'));
})->with(['home', 'dashboard', 'directory.index', 'commissions.index', 'members.index']);

test('they keep their household and get it back when it is reactivated', function () {
    $household = Household::factory()->inactive()->create(['name' => 'Cal Pere']);
    $user = User::factory()->member($household)->create();

    expect($user->member->fresh()->household_id)->toBe($household->id);

    $household->forceFill(['is_active' => true])->save();

    $this->actingAs($user->fresh())->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('household.name', 'Cal Pere'));
});

test('with a commission they have no household page but their commissions and roles work', function () {
    $user = inactiveHouseholdUser();
    $commission = Commission::factory()->create(['name' => 'Compres']);
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($user->member);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('household', null)
            ->has('commissions', 1));
    $this->get(route('members.index'))->assertOk();
});

test('an administrator who is an active member of an inactive household signs in straight as administrator', function () {
    $user = User::factory()->administrator()->member(Household::factory()->inactive()->create())->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('home'));
    $this->get(route('home'))->assertRedirect(route('admin.home'));
});

test('the directory lists only active households and their active members', function () {
    $administrator = User::factory()->administrator()->create();
    $active = Household::factory()->create(['name' => 'Cal Actiu']);
    User::factory()->member($active)->create(['name' => 'Anna Puig']);
    $inactive = Household::factory()->inactive()->create(['name' => 'Cal Inactiu']);
    User::factory()->member($inactive)->create(['name' => 'Pau Dins']);

    $this->actingAs($administrator)->get(route('directory.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('households', 1)
            ->where('households.0.name', 'Cal Actiu'));
});

test('the filter of members without a household also shows active members of an inactive household, flagged', function () {
    $household = Household::factory()->create();
    $inactiveHousehold = Household::factory()->inactive()->create();
    inactiveHouseholdMemberIn($household, true, 'Amb');
    inactiveHouseholdMemberIn(null, true, 'Sense');
    inactiveHouseholdMemberIn($inactiveHousehold, true, 'Dins inactiva');
    inactiveHouseholdMemberIn($inactiveHousehold, false, 'Inactiu dins inactiva');

    $this->actingAs(inactiveHouseholdMembershipActor())->get(route('members.index', ['filter' => 'no-household']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pagination.total', 2)
            ->where('members', fn ($members) => collect($members)->pluck('name')->all() === ['Dins inactiva', 'Sense'])
            ->where('members.0.household_is_active', false)
            ->where('members.1.household_is_active', null));
});

test('a member can be reactivated while their household is inactive and is then treated as without a household', function () {
    $member = inactiveHouseholdMemberIn(Household::factory()->inactive()->create(), false, 'Pau');

    $this->actingAs(inactiveHouseholdMembershipActor())
        ->post(route('members.reactivate', $member))
        ->assertSessionHasNoErrors();

    expect($member->fresh()->is_active)->toBeTrue();
    $this->actingAs($member->user)->get(route('dashboard'))->assertRedirect(route('waiting'));
});

test('inactive members can be removed from their household', function () {
    $member = inactiveHouseholdMemberIn(Household::factory()->create(), false, 'Pau');

    $this->actingAs(inactiveHouseholdMembershipActor())
        ->delete(route('members.household.destroy', $member))
        ->assertSessionHasNoErrors();

    expect($member->fresh()->household_id)->toBeNull();
});

function inactiveHouseholdMemberIn(?Household $household, bool $active, string $name): Member
{
    $factory = $household === null ? User::factory()->memberWithoutHousehold() : User::factory()->member($household);
    $member = $factory->create(['name' => $name])->member->fresh();
    $member->update(['is_active' => $active]);

    return $member->fresh();
}
