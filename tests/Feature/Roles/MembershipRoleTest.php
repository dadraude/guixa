<?php

use App\Auth\ActiveProfile;
use App\Auth\Profile;
use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

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
 * Every membership-management request, as [label => [request, expected redirect route or null for a 200 page]].
 *
 * @return array<string, array{Closure(User): TestResponse, ?string}>
 */
function membershipRequests(): array
{
    $household = Household::factory()->create(['name' => 'Cal Pere']);
    $invitation = Invitation::factory()->for($household)->create();
    $commission = Commission::factory()->create(['name' => 'Neteja']);
    $target = Member::factory()->create(['is_active' => true]);

    return [
        'list households' => [fn (User $user) => test()->actingAs($user)->get(route('households.index')), null],
        'create household' => [fn (User $user) => test()->actingAs($user)->post(route('households.store'), ['name' => 'Nova']), 'households.index'],
        'rename household' => [fn (User $user) => test()->actingAs($user)->patch(route('households.update', $household), ['name' => 'Cal Pere Nou']), 'households.index'],
        'invite' => [fn (User $user) => test()->actingAs($user)->post(route('households.invitations.store', $household), ['email' => fake()->unique()->safeEmail()]), 'households.index'],
        'list invitations' => [fn (User $user) => test()->actingAs($user)->get(route('households.invitations.index')), null],
        'resend invitation' => [fn (User $user) => test()->actingAs($user)->post(route('households.invitations.resend', $invitation)), 'households.invitations.index'],
        'cancel invitation' => [fn (User $user) => test()->actingAs($user)->delete(route('households.invitations.cancel', $invitation)), 'households.invitations.index'],
        'create commission' => [fn (User $user) => test()->actingAs($user)->post(route('commissions.store'), ['name' => 'Altra']), 'commissions.index'],
        'rename commission' => [fn (User $user) => test()->actingAs($user)->patch(route('commissions.update', $commission), ['name' => 'Neteja 2']), 'commissions.index'],
        'add commission member' => [fn (User $user) => test()->actingAs($user)->post(route('commissions.members.store', $commission), ['member_id' => $target->id]), 'commissions.index'],
        'remove commission member' => [fn (User $user) => test()->actingAs($user)->delete(route('commissions.members.destroy', [$commission, $target])), 'commissions.index'],
        'delete commission' => [fn (User $user) => test()->actingAs($user)->delete(route('commissions.destroy', $commission)), 'commissions.index'],
    ];
}

function expectEveryMembershipRequestToSucceed(User $user): void
{
    foreach (membershipRequests() as $label => [$request, $redirect]) {
        $response = $request($user);

        $redirect === null
            ? expect($response->status())->toBe(200, $label)
            : expect($response->isRedirect(route($redirect)))->toBeTrue($label);
    }
}

function expectEveryMembershipRequestToBeRefused(User $user): void
{
    foreach (membershipRequests() as $label => [$request]) {
        expect($request($user)->status())->toBe(403, $label);
    }
}

function actingAsProfile(User $user, Profile $profile): User
{
    test()->withSession(['active_profile' => $profile->value]);

    return $user->fresh();
}

function withAdministratorProfile(Member $member): User
{
    $member->user->administrator()->create(['is_active' => true]);

    return $member->user->fresh();
}

test('a member in a commission with the membership role manages households, invitations and commissions', function () {
    expectEveryMembershipRequestToSucceed(memberWithRoles(Role::Membership)->user);
});

test('a member without the membership role is refused every one of those requests', function () {
    expectEveryMembershipRequestToBeRefused(memberWithRoles(Role::Orders, Role::Boxes, Role::Treasury)->user);
});

test('a member in no commission is refused', function () {
    expectEveryMembershipRequestToBeRefused(memberWithRoles()->user);
});

test('the permission goes away as soon as the member leaves the commission or the commission loses the role', function () {
    $member = memberWithRoles(Role::Membership);
    $commission = $member->commissions()->sole();

    expect(Gate::forUser($member->user)->allows('manage-membership'))->toBeTrue();

    // Roles are remembered per request, so each step below stands for a new request.
    $commission->assignRole(Role::Orders);
    $commission->removeRole(Role::Membership);
    app()->forgetScopedInstances();
    expect(Gate::forUser($member->user->fresh())->allows('manage-membership'))->toBeFalse();

    $commission->assignRole(Role::Membership);
    app()->forgetScopedInstances();
    expect(Gate::forUser($member->user->fresh())->allows('manage-membership'))->toBeTrue();

    $commission->members()->detach($member);
    app()->forgetScopedInstances();
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

test('a single request resolves the member roles with one set of queries', function () {
    $member = memberWithRoles(Role::Membership);

    DB::enableQueryLog();
    $this->actingAs($member->user)->get(route('commissions.index'))->assertOk();
    $roleQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query) => str_contains($query['query'], 'commission_role') && str_contains($query['query'], 'distinct'))
        ->count();
    DB::disableQueryLog();

    expect($roleQueries)->toBe(1);
});

test('acting as member without the membership role, a two-profile user is refused membership and role actions', function () {
    $user = actingAsProfile(withAdministratorProfile(memberWithRoles(Role::Orders)), Profile::Member);
    $commission = Commission::factory()->create();

    expectEveryMembershipRequestToBeRefused($user);
    $this->actingAs($user)->post(route('commissions.roles.store', $commission), ['role' => 'orders'])->assertForbidden();
    $this->actingAs($user)->get(route('commissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false)->where('candidates', []));
});

test('acting as member with the membership role, a two-profile user manages membership but cannot assign roles', function () {
    $user = actingAsProfile(withAdministratorProfile(memberWithRoles(Role::Membership)), Profile::Member);
    $commission = Commission::factory()->create();

    expectEveryMembershipRequestToSucceed($user);
    $this->actingAs($user)->post(route('commissions.roles.store', $commission), ['role' => 'orders'])->assertForbidden();
    $this->actingAs($user)->delete(route('commissions.roles.destroy', [$commission, 'orders']))->assertForbidden();
});

test('acting as administrator, a two-profile user manages membership and assigns roles', function () {
    $user = actingAsProfile(withAdministratorProfile(memberWithRoles(Role::Orders)), Profile::Administrator);
    $commission = Commission::factory()->create();

    expectEveryMembershipRequestToSucceed($user);
    $this->actingAs($user)->post(route('commissions.roles.store', $commission), ['role' => 'orders'])->assertRedirect(route('commissions.index'));
    expect($commission->roles())->toBe([Role::Orders]);
});
