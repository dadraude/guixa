<?php

declare(strict_types=1);

use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function membersListQueryCount(User $viewer): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->actingAs($viewer)->get(route('members.index'))->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function addMembersWithCommissions(int $count): void
{
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);

    foreach (range(1, $count) as $ignored) {
        $member = User::factory()->member(Household::factory()->create())->create()->member->fresh();
        $member->update(['is_active' => true]);
        Commission::factory()->create()->members()->attach($member);
    }
}

test('the members list runs the same number of queries for a viewer with the membership role whatever the number of members', function () {
    $viewer = membershipRoleUser();

    addMembersWithCommissions(4);
    membersListQueryCount($viewer); // warm-up: first request boots caches
    $fewMembers = membersListQueryCount($viewer);

    addMembersWithCommissions(8);

    expect(membersListQueryCount($viewer))->toBe($fewMembers);
});

test('the members list runs the same number of queries for an administrator whatever the number of members', function () {
    $viewer = User::factory()->owner()->create();

    addMembersWithCommissions(4);
    membersListQueryCount($viewer);
    $fewMembers = membersListQueryCount($viewer);

    addMembersWithCommissions(8);

    expect(membersListQueryCount($viewer))->toBe($fewMembers);
});
