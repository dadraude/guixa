<?php

declare(strict_types=1);

use App\Auth\CommissionMemberships;
use App\Auth\CommissionRoles;
use App\Auth\MemberRoles;
use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function freshMemberRoles(): MemberRoles
{
    return new MemberRoles(new CommissionMemberships, new CommissionRoles);
}

function preloadMember(bool $active = true): Member
{
    $member = User::factory()->member(Household::factory()->create())->create()->member->fresh();
    $member->update(['is_active' => $active]);

    return $member->fresh();
}

function preloadCommission(Role ...$roles): Commission
{
    $commission = Commission::factory()->create();

    foreach ($roles as $role) {
        $commission->assignRole($role);
    }

    return $commission;
}

test('roles after a bulk preload equal the roles a fresh lookup gives, in the same order', function () {
    $none = preloadMember();
    $one = preloadMember();
    $two = preloadMember();
    $secondOnly = preloadMember();
    $inactive = preloadMember(false);

    preloadCommission(Role::Treasury, Role::Orders, Role::Membership)->members()->attach([$one->id, $inactive->id]);
    preloadCommission(Role::Boxes, Role::Orders)->members()->attach($two);
    preloadCommission()->members()->attach($two);
    preloadCommission()->members()->attach($secondOnly);
    preloadCommission(Role::Membership, Role::Boxes)->members()->attach($secondOnly);

    $members = [$none, $one, $two, $secondOnly, $inactive];
    $preloaded = freshMemberRoles();
    $preloaded->preload($members);

    foreach ($members as $member) {
        $fresh = freshMemberRoles();
        expect($preloaded->of($member))->toBe($fresh->of($member));
    }

    expect($preloaded->of($secondOnly))->toBe([Role::Boxes, Role::Membership])
        ->and($preloaded->of($inactive))->toBe([])
        ->and($preloaded->of($none))->toBe([]);
});

test('preload never overwrites roles already remembered', function () {
    $member = preloadMember();
    preloadCommission(Role::Orders)->members()->attach($member);
    $roles = app(MemberRoles::class);

    expect($roles->of($member))->toBe([Role::Orders]);

    preloadCommission(Role::Boxes)->members()->attach($member);
    $roles->preload([$member]);

    expect($roles->of($member))->toBe([Role::Orders]);
});

test('the list tells what a membership viewer may do with a member who holds the role only through a second commission', function () {
    $viewer = membershipRoleUser();
    $holder = preloadMember();
    $plain = preloadMember();
    preloadCommission(Role::Orders)->members()->attach($holder);
    preloadCommission(Role::Membership)->members()->attach($holder);

    $this->actingAs($viewer)
        ->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => collect($members)->firstWhere('id', $holder->id)['can_change_email'] === false
                && collect($members)->firstWhere('id', $holder->id)['can_deactivate'] === false
                && collect($members)->firstWhere('id', $plain->id)['can_change_email'] === true
                && collect($members)->firstWhere('id', $plain->id)['can_deactivate'] === true));
});
