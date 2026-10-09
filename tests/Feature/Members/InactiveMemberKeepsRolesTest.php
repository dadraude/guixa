<?php

declare(strict_types=1);

use App\Auth\MemberRoles;
use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function inactiveStaff(): User
{
    $user = User::factory()->member(Household::factory()->create())->create();
    $user->member->update(['is_active' => true]);
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($user->member->fresh());

    return $user->fresh();
}

function inactiveMemberHolding(Role ...$roles): Member
{
    $member = User::factory()->member(Household::factory()->create())->create()->member->fresh();
    $member->update(['is_active' => false]);
    $commission = Commission::factory()->create();

    foreach ($roles as $role) {
        $commission->assignRole($role);
    }

    $commission->members()->attach($member);

    return $member;
}

test('an inactive member keeps their roles for protections but grants nothing while inactive', function () {
    $member = inactiveMemberHolding(Role::Membership);
    $roles = app(MemberRoles::class);

    expect($roles->memberHolds($member, Role::Membership))->toBeTrue()
        ->and($roles->memberHas($member, Role::Membership))->toBeFalse()
        ->and($roles->userHas($member->user, Role::Membership))->toBeFalse();
});

test('a member with the membership role cannot change the email of an inactive member who keeps the membership role', function () {
    $target = inactiveMemberHolding(Role::Membership);

    $this->actingAs(inactiveStaff())
        ->patch(route('members.update', $target), ['name' => 'Same', 'email' => 'new@example.com', 'phone' => '600000000'])
        ->assertForbidden();

    expect($target->user->fresh()->email)->not->toBe('new@example.com');
});

test('a member with the membership role still changes the email of an inactive member without it', function () {
    $target = inactiveMemberHolding(Role::Orders);

    $this->actingAs(inactiveStaff())
        ->patch(route('members.update', $target), ['name' => 'Same', 'email' => 'new@example.com', 'phone' => '600000000'])
        ->assertRedirect(route('members.index'));

    expect($target->user->fresh()->email)->toBe('new@example.com');
});

test('the membership role cannot remove an inactive member from a commission holding the membership role', function () {
    $target = inactiveMemberHolding(Role::Membership);
    $commission = $target->commissions()->sole();

    $this->actingAs(inactiveStaff())
        ->delete(route('commissions.members.destroy', [$commission, $target]))
        ->assertForbidden();

    expect($target->commissions()->count())->toBe(1);
});

test('a member with the membership role cannot reactivate a member who holds the membership role', function () {
    $target = inactiveMemberHolding(Role::Membership);

    $this->actingAs(inactiveStaff())->post(route('members.reactivate', $target))->assertForbidden();

    expect($target->fresh()->is_active)->toBeFalse();
});

test('a member with the membership role cannot reactivate an administrator', function () {
    $target = inactiveMemberHolding(Role::Orders);
    $target->user->administrator()->create();

    $this->actingAs(inactiveStaff())->post(route('members.reactivate', $target))->assertForbidden();

    expect($target->fresh()->is_active)->toBeFalse();
});

test('a member with the membership role reactivates a member without those, who keeps their commissions', function () {
    $target = inactiveMemberHolding(Role::Orders);

    $this->actingAs(inactiveStaff())
        ->post(route('members.reactivate', $target))
        ->assertRedirect(route('members.index'));

    expect($target->fresh()->is_active)->toBeTrue()
        ->and($target->commissions()->count())->toBe(1);
});

test('administrators and the owner reactivate members who hold the membership role', function (Closure $actor) {
    $target = inactiveMemberHolding(Role::Membership);

    $this->actingAs($actor())->post(route('members.reactivate', $target))->assertRedirect(route('members.index'));

    expect($target->fresh()->is_active)->toBeTrue();
})->with([
    'administrator' => fn () => User::factory()->administrator()->create(),
    'owner' => fn () => User::factory()->owner()->create(),
]);

test('the members list tells the membership role who may be reactivated', function () {
    inactiveMemberHolding(Role::Membership);

    $this->actingAs(inactiveStaff())
        ->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page->where('members', fn ($members) => collect($members)
            ->where('is_active', false)
            ->every(fn ($member) => $member['can_reactivate'] === false)));
});

test('an inactive member in a commission does not clear the households warning', function () {
    $household = Household::factory()->create();
    $inactive = User::factory()->member($household)->create()->member;
    $inactive->update(['is_active' => false]);
    Commission::factory()->create()->members()->attach($inactive);

    $this->actingAs(User::factory()->administrator()->create())
        ->get(route('households.index'))
        ->assertInertia(fn (Assert $page) => $page->where('households.0.without_commission', true));
});
