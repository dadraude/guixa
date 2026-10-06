<?php

declare(strict_types=1);

use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function levelsMembershipUser(): User
{
    $user = User::factory()->member(Household::factory()->create())->create();
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($user->member->fresh());

    return $user->fresh();
}

function levelsTarget(?string $email = null): Member
{
    $member = User::factory()->member(Household::factory()->create())->create(['email' => $email ?? fake()->unique()->safeEmail()])->member->fresh();
    $member->update(['is_active' => true]);

    return $member;
}

function levelsAdministratorTarget(): Member
{
    $member = levelsTarget();
    $member->user->administrator()->create(['is_active' => true]);

    return $member;
}

function levelsMembershipTarget(): Member
{
    $member = levelsTarget();
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($member);

    return $member;
}

function changeEmail(User $actor, Member $target, string $email = 'new@example.com')
{
    return test()->actingAs($actor)
        ->patch(route('members.update', $target), ['name' => 'Same Name', 'email' => $email, 'phone' => '600000000']);
}

test('the owner changes the email of a member at once', function () {
    $target = levelsTarget();

    changeEmail(User::factory()->owner()->create(), $target)->assertRedirect(route('members.index'));

    expect($target->user->fresh()->email)->toBe('new@example.com');
});

test('the owner changes the email of an administrator and of a member with the membership role', function () {
    $owner = User::factory()->owner()->create();
    $administrator = levelsAdministratorTarget();
    $membership = levelsMembershipTarget();

    changeEmail($owner, $administrator, 'admin-new@example.com')->assertSessionDoesntHaveErrors();
    changeEmail($owner, $membership, 'role-new@example.com')->assertSessionDoesntHaveErrors();

    expect($administrator->user->fresh()->email)->toBe('admin-new@example.com')
        ->and($membership->user->fresh()->email)->toBe('role-new@example.com');
});

test('an administrator changes the email of a member who is not an administrator', function () {
    $target = levelsTarget();

    changeEmail(User::factory()->administrator()->create(), $target)->assertSessionDoesntHaveErrors();

    expect($target->user->fresh()->email)->toBe('new@example.com');
});

test('an administrator can change the email of a member with the membership role', function () {
    $target = levelsMembershipTarget();

    changeEmail(User::factory()->administrator()->create(), $target)->assertSessionDoesntHaveErrors();

    expect($target->user->fresh()->email)->toBe('new@example.com');
});

test('an administrator cannot change the email of another administrator or of the owner', function () {
    $administrator = User::factory()->administrator()->create();
    $otherAdministrator = levelsAdministratorTarget();
    $owner = User::factory()->owner()->create();
    $ownerAsMember = Member::factory()->for($owner)->create(['household_id' => Household::factory()->create()->id, 'is_active' => true]);

    changeEmail($administrator, $otherAdministrator)->assertForbidden();
    changeEmail($administrator, $ownerAsMember)->assertForbidden();

    expect($otherAdministrator->user->fresh()->email)->not->toBe('new@example.com')
        ->and($owner->fresh()->email)->not->toBe('new@example.com');
});

test('an administrator still edits name and phone of another administrator, leaving the email as it is', function () {
    $target = levelsAdministratorTarget();

    $this->actingAs(User::factory()->administrator()->create())
        ->patch(route('members.update', $target), ['name' => 'Renamed', 'email' => strtoupper($target->user->email), 'phone' => '611111111'])
        ->assertSessionDoesntHaveErrors();

    expect($target->user->fresh()->name)->toBe('Renamed')
        ->and($target->fresh()->phone)->toBe('611111111')
        ->and($target->user->fresh()->email)->not->toBe('new@example.com');
});

test('the membership role changes the email of a member without the role who is not an administrator', function () {
    $target = levelsTarget();

    changeEmail(levelsMembershipUser(), $target)->assertSessionDoesntHaveErrors();

    expect($target->user->fresh()->email)->toBe('new@example.com');
});

test('the membership role cannot change the email of a member with the role, an administrator or the owner', function () {
    $actor = levelsMembershipUser();
    $membership = levelsMembershipTarget();
    $administrator = levelsAdministratorTarget();
    $owner = User::factory()->owner()->create();
    $ownerAsMember = Member::factory()->for($owner)->create(['household_id' => Household::factory()->create()->id, 'is_active' => true]);

    changeEmail($actor, $membership)->assertForbidden();
    changeEmail($actor, $administrator)->assertForbidden();
    changeEmail($actor, $ownerAsMember)->assertForbidden();

    expect($membership->user->fresh()->email)->not->toBe('new@example.com')
        ->and($administrator->user->fresh()->email)->not->toBe('new@example.com');
});

test('the membership role still edits name and phone of any member', function () {
    $target = levelsMembershipTarget();

    $this->actingAs(levelsMembershipUser())
        ->patch(route('members.update', $target), ['name' => 'Renamed', 'email' => $target->user->email, 'phone' => '622222222'])
        ->assertSessionDoesntHaveErrors();

    expect($target->user->fresh()->name)->toBe('Renamed')
        ->and($target->fresh()->phone)->toBe('622222222');
});

test('the membership role cannot change its own email on the members screen', function () {
    $actor = levelsMembershipUser();

    changeEmail($actor, $actor->member)->assertForbidden();

    expect($actor->fresh()->email)->not->toBe('new@example.com');
});

test('an administrator acting as member only has the membership role level', function () {
    $actor = levelsMembershipUser();
    $actor->administrator()->create(['is_active' => true]);
    $target = levelsMembershipTarget();

    $this->actingAs($actor->fresh())->withSession(['active_profile' => 'member']);
    changeEmail($actor->fresh(), $target)->assertForbidden();
});

test('a duplicate email in any case is a validation error for every level', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $target = levelsTarget();

    changeEmail(User::factory()->owner()->create(), $target, 'TAKEN@Example.com')->assertSessionHasErrors('email');
    changeEmail(User::factory()->administrator()->create(), $target, 'taken@example.com')->assertSessionHasErrors('email');
    changeEmail(levelsMembershipUser(), $target, 'Taken@example.com')->assertSessionHasErrors('email');

    expect($target->user->fresh()->email)->not->toBe('new@example.com');
});

test('a change made for someone else clears a pending self-service change', function () {
    $target = levelsTarget();
    $target->user->forceFill(['pending_email' => 'pending@example.com', 'pending_email_token_hash' => 'x', 'pending_email_expires_at' => now()->addHour()])->save();

    changeEmail(User::factory()->owner()->create(), $target);

    expect($target->user->fresh()->pending_email)->toBeNull();
});

test('the members page tells which emails the viewer may change', function () {
    $plain = levelsTarget('plain@example.com');
    $administrator = levelsAdministratorTarget();
    $administrator->user->update(['email' => 'adm@example.com']);

    $this->actingAs(User::factory()->administrator()->create())
        ->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => collect($members)->firstWhere('email', 'plain@example.com')['can_change_email'] === true
                && collect($members)->firstWhere('email', 'adm@example.com')['can_change_email'] === false));
});
