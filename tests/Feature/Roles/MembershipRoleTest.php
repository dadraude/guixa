<?php

use App\Auth\ActiveProfile;
use App\Auth\Profile;
use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

function memberWithRoles(Role ...$roles): Member
{
    $user = User::factory()->member(Household::factory()->create())->create(['email' => fake()->unique()->safeEmail()]);
    $user->member->update(['is_active' => true]);
    $member = $user->member->fresh();

    $commission = Commission::factory()->create();
    foreach ($roles as $role) {
        $commission->assignRole($role);
    }
    $commission->members()->attach($member);

    return $member;
}

/**
 * Every membership-management request, as [label => callable returning the response status].
 *
 * @return array<string, Closure(User): int>
 */
function membershipRequests(): array
{
    $household = Household::factory()->create(['name' => 'Cal Pere']);
    $invitation = Invitation::factory()->for($household)->create();
    $commission = Commission::factory()->create(['name' => 'Neteja']);
    $target = Member::factory()->create(['is_active' => true]);

    return [
        'list households' => fn (User $user) => test()->actingAs($user)->get(route('households.index'))->status(),
        'create household' => fn (User $user) => test()->actingAs($user)->post(route('households.store'), ['name' => 'Nova'])->status(),
        'rename household' => fn (User $user) => test()->actingAs($user)->patch(route('households.update', $household), ['name' => 'Cal Pere Nou'])->status(),
        'invite' => fn (User $user) => test()->actingAs($user)->post(route('households.invitations.store', $household), ['email' => fake()->unique()->safeEmail()])->status(),
        'list invitations' => fn (User $user) => test()->actingAs($user)->get(route('households.invitations.index'))->status(),
        'resend invitation' => fn (User $user) => test()->actingAs($user)->post(route('households.invitations.resend', $invitation))->status(),
        'cancel invitation' => fn (User $user) => test()->actingAs($user)->delete(route('households.invitations.cancel', $invitation))->status(),
        'create commission' => fn (User $user) => test()->actingAs($user)->post(route('commissions.store'), ['name' => 'Altra'])->status(),
        'rename commission' => fn (User $user) => test()->actingAs($user)->patch(route('commissions.update', $commission), ['name' => 'Neteja 2'])->status(),
        'add commission member' => fn (User $user) => test()->actingAs($user)->post(route('commissions.members.store', $commission), ['member_id' => $target->id])->status(),
        'remove commission member' => fn (User $user) => test()->actingAs($user)->delete(route('commissions.members.destroy', [$commission, $target]))->status(),
        'delete commission' => fn (User $user) => test()->actingAs($user)->delete(route('commissions.destroy', $commission))->status(),
    ];
}

test('a member in a commission with the membership role manages households, invitations and commissions', function () {
    $member = memberWithRoles(Role::Membership);

    foreach (membershipRequests() as $label => $request) {
        expect($request($member->user))->toBeLessThan(400, $label);
    }
});

test('a member without the membership role is refused every one of those requests', function () {
    $member = memberWithRoles(Role::Orders, Role::Boxes, Role::Treasury);

    foreach (membershipRequests() as $label => $request) {
        expect($request($member->user))->toBe(403, $label);
    }
});

test('a member in no commission is refused', function () {
    $member = memberWithRoles();

    foreach (membershipRequests() as $label => $request) {
        expect($request($member->user))->toBe(403, $label);
    }
});

test('the permission goes away as soon as the member leaves the commission or the commission loses the role', function () {
    $member = memberWithRoles(Role::Membership);
    $commission = $member->commissions()->sole();

    expect(Gate::forUser($member->user)->allows('manage-membership'))->toBeTrue();

    $commission->assignRole(Role::Orders);
    $commission->removeRole(Role::Membership);
    expect(Gate::forUser($member->user->fresh())->allows('manage-membership'))->toBeFalse();

    $commission->assignRole(Role::Membership);
    expect(Gate::forUser($member->user->fresh())->allows('manage-membership'))->toBeTrue();

    $commission->members()->detach($member);
    expect(Gate::forUser($member->user->fresh())->allows('manage-membership'))->toBeFalse();
});

test('the role comes from any of several commissions', function () {
    $member = memberWithRoles(Role::Orders);
    $second = Commission::factory()->create();
    $second->assignRole(Role::Membership);
    $second->members()->attach($member);

    expect(Gate::forUser($member->user)->allows('manage-membership'))->toBeTrue();
});

test('an inactive member never gets role permissions', function () {
    $member = memberWithRoles(Role::Membership);
    $member->update(['is_active' => false]);

    expect(Gate::forUser($member->user->fresh())->allows('manage-membership'))->toBeFalse();
});

test('someone with both profiles gets membership rights as member only through the role, and everything as administrator', function () {
    $withRole = memberWithRoles(Role::Membership)->user;
    $withRole->administrator()->create(['is_active' => true]);
    $withoutRole = memberWithRoles(Role::Orders)->user;
    $withoutRole->administrator()->create(['is_active' => true]);

    $actAs = fn (User $user, Profile $profile) => tap($user->fresh(), fn () => app(ActiveProfile::class)->choose($profile));

    expect(Gate::forUser($actAs($withRole, Profile::Member))->allows('manage-membership'))->toBeTrue()
        ->and(Gate::forUser($actAs($withRole, Profile::Member))->allows('assign-roles'))->toBeFalse()
        ->and(Gate::forUser($actAs($withoutRole, Profile::Member))->allows('manage-membership'))->toBeFalse()
        ->and(Gate::forUser($actAs($withoutRole, Profile::Administrator))->allows('manage-membership'))->toBeTrue()
        ->and(Gate::forUser($actAs($withoutRole, Profile::Administrator))->allows('assign-roles'))->toBeTrue();
});

test('role assignment is administrators only', function () {
    expect(Gate::forUser(User::factory()->create())->allows('assign-roles'))->toBeFalse()
        ->and(Gate::forUser(memberWithRoles(Role::Membership)->user)->allows('assign-roles'))->toBeFalse()
        ->and(Gate::forUser(User::factory()->administrator()->create())->allows('assign-roles'))->toBeTrue();
});

test('the shared props tell the sidebar whether the person manages membership', function () {
    $this->actingAs(memberWithRoles(Role::Membership)->user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.canManageMembership', true));
    $this->actingAs(memberWithRoles(Role::Orders)->user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.canManageMembership', false));
});
