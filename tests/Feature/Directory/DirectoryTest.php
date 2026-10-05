<?php

declare(strict_types=1);

use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function directoryMember(Household $household, string $name, bool $active = true, ?string $phone = null): Member
{
    $user = User::factory()->member($household)->create(['name' => $name, 'email' => fake()->unique()->safeEmail()]);
    $user->member->update(['is_active' => $active, 'phone' => $phone]);

    return $user->member->fresh();
}

function inACommission(Member $member): Member
{
    Commission::factory()->create()->members()->attach($member);

    return $member;
}

test('an administrator sees every household and member with contact details', function () {
    $administrator = User::factory()->administrator()->create();
    $first = Household::factory()->create(['name' => 'Cal Pere']);
    $second = Household::factory()->create(['name' => 'Ca la Maria']);
    $anna = directoryMember($first, 'Anna Puig', phone: '600111222');
    directoryMember($second, 'Joan Soler');

    $this->actingAs($administrator)
        ->get(route('directory.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('directory/Index')
            ->where('isFull', true)
            ->has('households', 2)
            ->where('households.0.name', 'Cal Pere')
            ->where('households.0.members.0.name', 'Anna Puig')
            ->where('households.0.members.0.email', $anna->user->email)
            ->where('households.0.members.0.phone', '600111222')
            ->where('households.1.name', 'Ca la Maria'));
});

test('a member in a commission sees the full directory', function () {
    $own = Household::factory()->create();
    $other = Household::factory()->create(['name' => 'Cal Altre']);
    $viewer = inACommission(directoryMember($own, 'Anna Puig'));
    directoryMember($other, 'Joan Soler', phone: '600333444');

    $this->actingAs($viewer->user)
        ->get(route('directory.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isFull', true)
            ->has('households', 2)
            ->where('households.1.members.0.phone', '600333444'));
});

test('a member in no commission sees only their own household', function () {
    $own = Household::factory()->create(['name' => 'Cal Pere']);
    $other = Household::factory()->create(['name' => 'Cal Altre']);
    $viewer = directoryMember($own, 'Anna Puig');
    directoryMember($own, 'Pau Puig');
    directoryMember($other, 'Joan Soler', phone: '600333444');

    $this->actingAs($viewer->user)
        ->get(route('directory.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isFull', false)
            ->has('households', 1)
            ->where('households.0.name', 'Cal Pere')
            ->has('households.0.members', 2));
});

test('an inactive member in a commission is signed out', function () {
    $own = Household::factory()->create();
    $viewer = inACommission(directoryMember($own, 'Anna Puig'));
    $viewer->update(['is_active' => false]);

    $this->actingAs($viewer->user)
        ->get(route('directory.index'))
        ->assertRedirect(route('login'));
});

test('inactive members are not listed in the directory', function () {
    $administrator = User::factory()->administrator()->create();
    $household = Household::factory()->create();
    directoryMember($household, 'Anna Puig');
    directoryMember($household, 'Pau Baixa', active: false);

    $this->actingAs($administrator)
        ->get(route('directory.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('households.0.members', 1)
            ->where('households.0.members.0.name', 'Anna Puig'));
});

test('guests are sent to log in', function () {
    $this->get(route('directory.index'))->assertRedirect(route('login'));
});

test('the households list flags active households with nobody in a commission', function () {
    $administrator = User::factory()->administrator()->create();
    $covered = Household::factory()->create(['name' => 'Coberta']);
    inACommission(directoryMember($covered, 'Anna Puig'));
    $uncovered = Household::factory()->create(['name' => 'Descoberta']);
    directoryMember($uncovered, 'Joan Soler');
    $empty = Household::factory()->create(['name' => 'Buida']);
    $inactiveOnly = Household::factory()->create(['name' => 'Nomes baixa']);
    inACommission(directoryMember($inactiveOnly, 'Marta Baixa', active: false));
    Household::factory()->inactive()->create(['name' => 'Inactiva']);

    $this->actingAs($administrator)
        ->get(route('households.index'))
        ->assertInertia(function (Assert $page) {
            $flags = collect($page->toArray()['props']['households'])->pluck('without_commission', 'name')->all();

            expect($flags)->toBe([
                'Coberta' => false,
                'Descoberta' => true,
                'Buida' => true,
                'Nomes baixa' => true,
                'Inactiva' => false,
            ]);
        });
});

test('the households list runs the same queries however many households there are', function () {
    $administrator = User::factory()->administrator()->create();
    $countQueries = function () use ($administrator): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($administrator)->get(route('households.index'))->assertOk();

        return count(DB::getQueryLog());
    };
    Household::factory()->create();
    $countQueries(); // warm-up: the first request also boots the session and auth
    $withOne = $countQueries();

    Household::factory()->count(5)->create();

    expect($countQueries())->toBe($withOne);
});

test('the membership role also sees the households warning', function () {
    $household = Household::factory()->create();
    $viewer = directoryMember($household, 'Anna Puig');
    $commission = Commission::factory()->create();
    $commission->members()->attach($viewer);
    $commission->assignRole(Role::Membership);

    $this->actingAs($viewer->user)
        ->get(route('households.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('households.0.without_commission', false));
});
