<?php

declare(strict_types=1);

use App\Actions\ChangeHouseholdMembersStatus;
use App\Actions\DeactivateHousehold;
use App\Actions\ReactivateHousehold;
use App\Auth\MemberRoles;
use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('refuses to run outside the transaction that locked the household', function () {
    $household = Household::factory()->create();

    // RefreshDatabase wraps every test in a transaction, so leave it to reach level zero
    // and open it again afterwards so the framework can still roll back at teardown.
    DB::rollBack();

    try {
        expect(fn () => app(ChangeHouseholdMembersStatus::class)->handle($household, true, fn () => true, fn () => null))
            ->toThrow(LogicException::class);
    } finally {
        DB::beginTransaction();
    }
});

// The roles are remembered per request; the permission predicate must read them inside the lock.
function memberWithRolesMemoizedAsNone(bool $active): array
{
    $household = Household::factory()->create(['is_active' => $active]);
    $member = User::factory()->member($household)->create()->member;
    $member->update(['is_active' => $active]);
    $actor = User::factory()->member(Household::factory()->create())->create();

    app(MemberRoles::class)->heldBy($member);

    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($member);

    return [$household, $member, $actor];
}

it('skips on deactivation a member whose membership role was memoized as none before the lock', function () {
    [$household, $member, $actor] = memberWithRolesMemoizedAsNone(active: true);

    $change = app(DeactivateHousehold::class)->handle($household, $actor, withMembers: true);

    expect($change->skippedNames)->toBe([$member->user->name])
        ->and($member->fresh()->is_active)->toBeTrue();
});

it('skips on reactivation a member whose membership role was memoized as none before the lock', function () {
    [$household, $member, $actor] = memberWithRolesMemoizedAsNone(active: false);

    $change = app(ReactivateHousehold::class)->handle($household, $actor, withMembers: true);

    expect($change->skippedNames)->toBe([$member->user->name])
        ->and($member->fresh()->is_active)->toBeFalse();
});
