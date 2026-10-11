<?php

declare(strict_types=1);

use App\Actions\DeactivateMember;
use App\Actions\RemoveCommissionMember;
use App\Auth\MembershipRoleProtection;
use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use App\Support\MemberStatusChange;
use Inertia\Testing\AssertableInertia as Assert;

function protectionMembershipCommission(): Commission
{
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);

    return $commission;
}

function protectionMember(): Member
{
    $member = User::factory()->member(Household::factory()->create())->create()->member->fresh();
    $member->update(['is_active' => true]);

    return $member;
}

function protectionActor(): User
{
    $commission = protectionMembershipCommission();
    $user = User::factory()->member(Household::factory()->create())->create();
    $commission->members()->attach($user->member->fresh());

    return $user->fresh();
}

function protectionColleague(): Member
{
    $colleague = protectionMember();
    protectionMembershipCommission()->members()->attach($colleague);

    return $colleague;
}

test('removing a member from a commission holding the membership role is refused to the membership role', function () {
    $commission = protectionMembershipCommission();
    $colleague = protectionMember();
    $commission->members()->attach($colleague);

    $this->actingAs(protectionActor())
        ->delete(route('commissions.members.destroy', [$commission, $colleague]))
        ->assertForbidden();

    expect($commission->members()->whereKey($colleague->id)->exists())->toBeTrue();
});

test('an administrator and the owner remove a member from a commission holding the membership role', function (User $actor) {
    $commission = protectionMembershipCommission();
    $colleague = protectionMember();
    $commission->members()->attach($colleague);

    $this->actingAs($actor)
        ->delete(route('commissions.members.destroy', [$commission, $colleague]))
        ->assertRedirect(route('commissions.index'));

    expect($commission->members()->whereKey($colleague->id)->exists())->toBeFalse();
})->with([
    'administrator' => fn () => User::factory()->administrator()->create(),
    'owner' => fn () => User::factory()->owner()->create(),
]);

test('the membership role removes a member who keeps the role through another commission, themselves included', function (bool $themselves) {
    $commission = protectionMembershipCommission();
    $actor = protectionActor();
    $target = $themselves ? $actor->member : protectionColleague();
    $commission->members()->attach($target);

    $this->actingAs($actor)
        ->delete(route('commissions.members.destroy', [$commission, $target]))
        ->assertRedirect(route('commissions.index'));

    expect($commission->members()->whereKey($target->id)->exists())->toBeFalse();
})->with([
    'a colleague' => false,
    'themselves' => true,
]);

test('the membership role cannot remove a member whose only other commission lacks the role', function () {
    $commission = protectionMembershipCommission();
    $target = protectionMember();
    $commission->members()->attach($target);
    Commission::factory()->create()->members()->attach($target);

    $this->actingAs(protectionActor())
        ->delete(route('commissions.members.destroy', [$commission, $target]))
        ->assertForbidden();

    expect($commission->members()->whereKey($target->id)->exists())->toBeTrue();
});

test('removing a member from two membership commissions in a row is refused the second time', function () {
    $first = protectionMembershipCommission();
    $second = protectionMembershipCommission();
    $target = protectionMember();
    $target->commissions()->attach([$first->id, $second->id]);
    $actor = protectionActor();

    $this->actingAs($actor)
        ->delete(route('commissions.members.destroy', [$first, $target]))
        ->assertRedirect(route('commissions.index'));

    $this->actingAs($actor)
        ->delete(route('commissions.members.destroy', [$second, $target]))
        ->assertForbidden();

    expect($second->members()->whereKey($target->id)->exists())->toBeTrue();
});

test('the membership role cannot remove themselves from their only membership commission', function () {
    $actor = protectionActor();
    $commission = $actor->member->commissions()->firstOrFail();

    $this->actingAs($actor)
        ->delete(route('commissions.members.destroy', [$commission, $actor->member]))
        ->assertForbidden();

    expect($commission->members()->whereKey($actor->member->id)->exists())->toBeTrue();
});

test('removing a member who is not in the commission changes nothing', function () {
    $commission = protectionMembershipCommission();
    $outsider = protectionColleague();

    $this->actingAs(protectionActor())
        ->delete(route('commissions.members.destroy', [$commission, $outsider]))
        ->assertRedirect(route('commissions.index'));

    expect($outsider->commissions()->count())->toBe(1);
});

test('the removal re-checks the protection inside the transaction', function () {
    $staff = protectionActor();
    $commission = protectionMembershipCommission();
    $other = protectionMembershipCommission();
    $target = protectionMember();
    $target->commissions()->attach([$commission->id, $other->id]);
    $protection = app(MembershipRoleProtection::class);

    // What the caller saw: the member keeps the role through the other commission.
    expect($protection->mayRemoveMemberFromCommission($staff, $commission, $target))->toBeTrue();

    // A concurrent removal takes it away before ours runs.
    $other->members()->detach($target);

    expect(app(RemoveCommissionMember::class)->handleAs($commission, $target, $staff))->toBeFalse()
        ->and($commission->members()->whereKey($target->id)->exists())->toBeTrue();
});

test('the removal route answers 403 when the protection fails inside the transaction', function () {
    $commission = protectionMembershipCommission();
    $target = protectionColleague();
    $commission->members()->attach($target);

    $this->mock(RemoveCommissionMember::class)->shouldReceive('handleAs')->once()->andReturnFalse();

    $this->actingAs(protectionActor())
        ->delete(route('commissions.members.destroy', [$commission, $target]))
        ->assertForbidden();

    expect($commission->members()->whereKey($target->id)->exists())->toBeTrue();
});

test('an inactive member keeps the role through another commission as an active one does', function () {
    $commission = protectionMembershipCommission();
    $target = protectionColleague();
    $target->update(['is_active' => false]);
    $commission->members()->attach($target);

    $this->actingAs(protectionActor())
        ->delete(route('commissions.members.destroy', [$commission, $target]))
        ->assertRedirect(route('commissions.index'));

    expect($commission->members()->whereKey($target->id)->exists())->toBeFalse();
});

test('the commissions page shows the remove action only where the member keeps the role', function () {
    $commission = protectionMembershipCommission();
    $keeper = protectionColleague();
    $loser = protectionMember();
    $commission->members()->attach([$keeper->id, $loser->id]);

    $commissionsSeenBy = fn (User $viewer) => $this->actingAs($viewer)->get(route('commissions.index'))->inertiaProps('commissions');

    $members = collect($commissionsSeenBy(protectionActor()))->firstWhere('id', $commission->id)['members'];
    expect(collect($members)->firstWhere('id', $keeper->id)['can_remove'])->toBeTrue()
        ->and(collect($members)->firstWhere('id', $loser->id)['can_remove'])->toBeFalse();

    $members = collect($commissionsSeenBy(User::factory()->administrator()->create()))->firstWhere('id', $commission->id)['members'];
    expect(collect($members)->every(fn ($member) => $member['can_remove']))->toBeTrue();
});

test('the membership role still removes a member from a commission without the membership role', function () {
    $commission = Commission::factory()->create();
    $member = protectionMember();
    $commission->members()->attach($member);

    $this->actingAs(protectionActor())
        ->delete(route('commissions.members.destroy', [$commission, $member]))
        ->assertRedirect(route('commissions.index'));

    expect($commission->members()->whereKey($member->id)->exists())->toBeFalse();
});

test('deactivating a member who holds the membership role is refused to the membership role', function () {
    $colleague = protectionColleague();

    $this->actingAs(protectionActor())
        ->post(route('members.deactivate', $colleague))
        ->assertForbidden();

    expect($colleague->fresh()->is_active)->toBeTrue()
        ->and($colleague->commissions()->count())->toBe(1);
});

test('an administrator deactivates a member who holds the membership role', function () {
    $colleague = protectionColleague();

    $this->actingAs(User::factory()->administrator()->create())
        ->post(route('members.deactivate', $colleague))
        ->assertRedirect(route('members.index'));

    expect($colleague->fresh()->is_active)->toBeFalse();
});

test('the membership role still deactivates a member without the membership role', function () {
    $member = protectionMember();

    $this->actingAs(protectionActor())
        ->post(route('members.deactivate', $member))
        ->assertRedirect(route('members.index'));

    expect($member->fresh()->is_active)->toBeFalse();
});

test('an administrator acting as member is refused like the membership role', function () {
    $actor = protectionActor();
    $actor->administrator()->create();
    $colleague = protectionColleague();

    $this->withSession(['active_profile' => 'member'])
        ->actingAs($actor->fresh())
        ->post(route('members.deactivate', $colleague))
        ->assertForbidden();
});

test('deleting a commission holding the membership role is refused to the membership role', function () {
    $commission = protectionMembershipCommission();

    $this->actingAs(protectionActor())
        ->delete(route('commissions.destroy', $commission))
        ->assertForbidden();

    expect(Commission::query()->whereKey($commission->id)->exists())->toBeTrue();
});

test('an administrator deletes an empty commission holding the membership role', function () {
    $commission = protectionMembershipCommission();

    $this->actingAs(User::factory()->administrator()->create())
        ->delete(route('commissions.destroy', $commission))
        ->assertRedirect(route('commissions.index'));

    expect(Commission::query()->whereKey($commission->id)->exists())->toBeFalse();
});

test('the membership role still deletes an empty commission without the membership role', function () {
    $commission = Commission::factory()->create();

    $this->actingAs(protectionActor())
        ->delete(route('commissions.destroy', $commission))
        ->assertRedirect(route('commissions.index'));

    expect(Commission::query()->whereKey($commission->id)->exists())->toBeFalse();
});

test('deactivating a household with members leaves active the member who holds the membership role', function () {
    $colleague = protectionColleague();

    $this->actingAs(protectionActor())
        ->post(route('households.deactivate', $colleague->household_id), ['with_members' => true])
        ->assertRedirect(route('households.index'));

    expect($colleague->fresh()->is_active)->toBeTrue();
});

test('an administrator deactivates a household with a member who holds the membership role', function () {
    $colleague = protectionColleague();

    $this->actingAs(User::factory()->administrator()->create())
        ->post(route('households.deactivate', $colleague->household_id), ['with_members' => true])
        ->assertRedirect(route('households.index'));

    expect($colleague->fresh()->is_active)->toBeFalse();
});

test('the membership role still deactivates a household and a member without the membership role', function () {
    $member = protectionMember();

    $this->actingAs(protectionActor())
        ->post(route('households.deactivate', $member->household_id), ['with_members' => true])
        ->assertRedirect(route('households.index'));

    expect($member->fresh()->is_active)->toBeFalse();
});

test('the takeover sequence is refused at its first and second step', function () {
    $actor = protectionActor();
    $commission = protectionMembershipCommission();
    $colleague = protectionMember();
    $commission->members()->attach($colleague);
    $originalEmail = $colleague->user->email;

    $this->actingAs($actor)
        ->delete(route('commissions.members.destroy', [$commission, $colleague]))
        ->assertForbidden();
    $this->actingAs($actor)
        ->patch(route('members.update', $colleague), ['name' => 'Same Name', 'email' => 'taken@example.com', 'phone' => null])
        ->assertForbidden();

    expect($colleague->user->fresh()->email)->toBe($originalEmail);
});

test('the pages tell which of these actions the viewer may do', function () {
    $colleague = protectionColleague();
    $plain = protectionMember();
    $commission = protectionMembershipCommission();
    $viewer = protectionActor();

    $this->actingAs($viewer)
        ->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => collect($members)->firstWhere('id', $colleague->id)['can_deactivate'] === false
                && collect($members)->firstWhere('id', $plain->id)['can_deactivate'] === true));

    $this->actingAs($viewer)
        ->get(route('commissions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('commissions', fn ($commissions) => collect($commissions)->firstWhere('id', $commission->id)['can_delete'] === false));

    $this->actingAs(User::factory()->administrator()->create())
        ->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => collect($members)->every(fn ($member) => $member['can_deactivate'] === true)));
});

test('the membership role is refused a deactivation only because of active members who are administrators', function () {
    $actor = protectionActor();
    $active = User::factory()->administrator()->member(Household::factory()->create())->create()->member;
    $inactive = User::factory()->administrator()->member(Household::factory()->create())->create()->member;
    $inactive->update(['is_active' => false]);
    $removed = User::factory()->administrator()->member(Household::factory()->create())->create();
    $removed->administrator->delete();

    $this->actingAs($actor)->post(route('members.deactivate', $active))->assertForbidden();
    $this->actingAs($actor)->post(route('members.deactivate', $removed->member))->assertRedirect();
    $this->actingAs($actor)->post(route('members.deactivate', $inactive))->assertRedirect();

    expect($active->fresh()->is_active)->toBeTrue()
        ->and($inactive->fresh()->is_active)->toBeFalse()
        ->and($removed->member->fresh()->is_active)->toBeFalse();
});

test('an active member who is an administrator stays active when the membership role deactivates their household with members', function () {
    $actor = protectionActor();
    $blocked = Household::factory()->create();
    $active = User::factory()->administrator()->member($blocked)->create();
    $free = Household::factory()->create();
    $removed = User::factory()->administrator()->member($free)->create();
    $removed->administrator->delete();

    $this->actingAs($actor)->post(route('households.deactivate', $blocked), ['with_members' => true])->assertRedirect();
    $this->actingAs($actor)->post(route('households.deactivate', $free), ['with_members' => true])->assertRedirect();

    expect($active->member->fresh()->is_active)->toBeTrue()
        ->and($removed->member->fresh()->is_active)->toBeFalse();
});

test('the deactivation re-evaluates the protection inside the transaction, with fresh data', function () {
    $staff = protectionActor();
    $member = protectionMember();
    $protection = app(MembershipRoleProtection::class);

    // What the controller sees: a member the membership role may still deactivate.
    $stale = Member::query()->with('user.administrator')->findOrFail($member->id);
    expect($protection->mayDeactivateMember($staff, $stale))->toBeTrue();

    // The owner makes them an administrator after that check.
    $member->user->administrator()->create();

    expect(app(DeactivateMember::class)->handleAs($stale, $staff))->toBeFalse()
        ->and($member->fresh()->is_active)->toBeTrue();
});

test('the deactivation re-reads the roles held through commissions inside the transaction', function () {
    $staff = protectionActor();
    $member = protectionMember();
    $protection = app(MembershipRoleProtection::class);
    expect($protection->mayDeactivateMember($staff, $member))->toBeTrue();

    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($member);

    expect(app(DeactivateMember::class)->handleAs($member, $staff))->toBeFalse()
        ->and($member->fresh()->is_active)->toBeTrue();
});

test('the deactivation goes through when the protection still holds', function () {
    $member = protectionMember();

    expect(app(DeactivateMember::class)->handleAs($member, protectionActor()))->toBeTrue()
        ->and($member->fresh()->is_active)->toBeFalse();
});

test('the deactivate route answers 403 when the protection fails inside the transaction', function () {
    $member = protectionMember();

    // The controller's own check passes; the locked re-check refuses.
    $this->mock(DeactivateMember::class)->shouldReceive('changeAs')->once()->andReturn(MemberStatusChange::Forbidden);

    $this->actingAs(protectionActor())
        ->post(route('members.deactivate', $member))
        ->assertForbidden()
        ->assertSessionMissing('inertia.flash_data');

    expect($member->fresh()->is_active)->toBeTrue();
});
