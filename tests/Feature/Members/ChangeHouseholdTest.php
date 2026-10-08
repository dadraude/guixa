<?php

declare(strict_types=1);

use App\Actions\ChangeMemberHousehold;
use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function membershipActor(): User
{
    $user = User::factory()->member(Household::factory()->create())->create();
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($user->member->fresh());

    return $user->fresh();
}

function memberWith(?Household $household, bool $active = true, array $user = []): Member
{
    $factory = $household === null ? User::factory()->memberWithoutHousehold() : User::factory()->member($household);
    $member = $factory->create($user)->member->fresh();
    $member->update(['is_active' => $active]);

    return $member->fresh();
}

test('the same action assigns a first household and moves, with one success message', function () {
    $first = Household::factory()->create();
    $second = Household::factory()->create();
    $member = memberWith(null);
    $actor = membershipActor();

    $this->actingAs($actor)->patch(route('members.household.update', $member), ['household_id' => $first->id])
        ->assertSessionHas('inertia.flash_data.toast.message', __('Household changed.'));
    expect($member->fresh()->household_id)->toBe($first->id);

    $this->patch(route('members.household.update', $member), ['household_id' => $second->id])
        ->assertSessionHas('inertia.flash_data.toast.message', __('Household changed.'));
    expect($member->fresh()->household_id)->toBe($second->id);
});

test('changing the household of an inactive member keeps them inactive and they can be reactivated there', function () {
    $old = Household::factory()->inactive()->create();
    $new = Household::factory()->create();
    $member = memberWith($old, false);
    $actor = membershipActor();

    $this->actingAs($actor)->post(route('members.reactivate', $member))->assertSessionHasErrors('member');

    $this->patch(route('members.household.update', $member), ['household_id' => $new->id])->assertSessionDoesntHaveErrors();
    expect($member->fresh()->is_active)->toBeFalse()
        ->and($member->fresh()->household_id)->toBe($new->id);

    $this->post(route('members.reactivate', $member))->assertSessionDoesntHaveErrors();
    expect($member->fresh()->is_active)->toBeTrue();
});

test('the action rejects a household that is no longer active when it checks under the lock', function () {
    $member = memberWith(Household::factory()->create());
    $target = Household::factory()->create();
    $target->forceFill(['is_active' => false])->save();

    expect(fn () => app(ChangeMemberHousehold::class)->handle($member, $target->id))->toThrow(ValidationException::class)
        ->and($member->fresh()->household_id)->not->toBe($target->id);
});

test('the action locks the target household inside a transaction', function () {
    $member = memberWith(null);
    $target = Household::factory()->create();
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    app(ChangeMemberHousehold::class)->handle($member, $target->id);

    $lockQuery = collect($queries)->first(fn (string $sql) => str_contains($sql, 'from "households"') || str_contains($sql, 'from `households`'));
    expect($lockQuery)->not->toBeNull();
    if (DB::getDriverName() !== 'sqlite') {
        expect($lockQuery)->toContain('for update');
    }
});

test('two consecutive changes on the same member end with the last one winning', function () {
    $a = Household::factory()->create();
    $b = Household::factory()->create();
    $member = memberWith(null);

    app(ChangeMemberHousehold::class)->handle($member, $a->id);
    app(ChangeMemberHousehold::class)->handle($member, $b->id);

    expect($member->fresh()->household_id)->toBe($b->id);
});

test('removing a member from their household keeps their commissions', function () {
    $member = memberWith(Household::factory()->create());
    $commission = Commission::factory()->create();
    $commission->members()->attach($member);

    $this->actingAs(membershipActor())
        ->delete(route('members.household.destroy', $member))
        ->assertRedirect(route('members.index'))
        ->assertSessionHas('inertia.flash_data.toast.message', __('Member removed from the household.'));

    expect($member->fresh()->household_id)->toBeNull()
        ->and($member->fresh()->commissions()->count())->toBe(1);
});

test('only active members can be removed from their household', function () {
    $household = Household::factory()->create();
    $member = memberWith($household, false);

    $this->actingAs(membershipActor())
        ->delete(route('members.household.destroy', $member))
        ->assertSessionHasErrors('member');

    expect($member->fresh()->household_id)->toBe($household->id);
});

test('removal takes effect on the next request: no household and no commission lands on the waiting screen', function () {
    $user = User::factory()->member(Household::factory()->create())->create();
    $actor = membershipActor();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->actingAs($actor)->delete(route('members.household.destroy', $user->member));

    $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('waiting'));
});

test('the membership role changes and removes the household of anyone, including themselves and administrators', function () {
    $actor = membershipActor();
    $target = Household::factory()->create();
    $administrator = memberWith(Household::factory()->create());
    $administrator->user->administrator()->create();

    $this->actingAs($actor)->patch(route('members.household.update', $administrator), ['household_id' => $target->id])->assertSessionDoesntHaveErrors();
    expect($administrator->fresh()->household_id)->toBe($target->id);

    $this->delete(route('members.household.destroy', $administrator))->assertSessionDoesntHaveErrors();
    expect($administrator->fresh()->household_id)->toBeNull();

    $this->patch(route('members.household.update', $actor->member), ['household_id' => $target->id])->assertSessionDoesntHaveErrors();
    expect($actor->member->fresh()->household_id)->toBe($target->id);

    $this->delete(route('members.household.destroy', $actor->member))->assertSessionDoesntHaveErrors();
    expect($actor->member->fresh()->household_id)->toBeNull();
});

test('a member without the role cannot change or remove a household', function () {
    $household = Household::factory()->create();
    $member = memberWith($household);
    $plain = User::factory()->member(Household::factory()->create())->create();

    $this->actingAs($plain)->patch(route('members.household.update', $member), ['household_id' => Household::factory()->create()->id])->assertForbidden();
    $this->actingAs($plain)->delete(route('members.household.destroy', $member))->assertForbidden();
    expect($member->fresh()->household_id)->toBe($household->id);
});

test('the members list can be filtered to active members without a household', function () {
    $household = Household::factory()->create();
    memberWith($household, true, ['name' => 'Amb']);
    memberWith(null, true, ['name' => 'Sense']);
    memberWith(null, false, ['name' => 'Inactiu sense']);

    $this->actingAs(membershipActor())->get(route('members.index', ['filter' => 'no-household']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filter', 'no-household')
            ->where('pagination.total', 1)
            ->where('members', fn ($members) => collect($members)->pluck('name')->all() === ['Sense']));
});

test('the filter keeps the sorting and pagination of the list and survives member actions', function () {
    $household = Household::factory()->create();
    foreach (range(1, 17) as $number) {
        memberWith(null, true, ['name' => sprintf('Zzz%02d', $number)]);
    }
    $actor = membershipActor();

    $this->actingAs($actor)->get(route('members.index', ['filter' => 'no-household']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('members', 15)
            ->where('pagination.last_page', 2)
            ->where('members.0.name', 'Zzz01')
            ->where('pagination.next_page_url', fn ($url) => str_contains($url, 'filter=no-household')));

    $member = Member::query()->whereNull('household_id')->where('is_active', true)->firstOrFail();
    $this->from(route('members.index', ['filter' => 'no-household', 'page' => 2]))
        ->patch(route('members.household.update', $member), ['household_id' => $household->id])
        ->assertRedirect(route('members.index', ['filter' => 'no-household', 'page' => 2]));
});

test('an unknown filter value shows the whole list', function () {
    memberWith(Household::factory()->create(), true, ['name' => 'Amb']);

    $this->actingAs(membershipActor())->get(route('members.index', ['filter' => 'bogus']))
        ->assertInertia(fn (Assert $page) => $page->where('filter', null)->where('members', fn ($m) => collect($m)->pluck('name')->contains('Amb')));
});
