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
    $owner = User::factory()->member(Household::factory()->create())->owner()->create();
    $ownerAsMember = $owner->member;

    $otherEmail = $otherAdministrator->user->email;
    $ownerEmail = $owner->email;

    changeEmail($administrator, $otherAdministrator)->assertForbidden();
    changeEmail($administrator, $ownerAsMember)->assertForbidden();

    expect($otherAdministrator->user->fresh()->email)->toBe($otherEmail)
        ->and($owner->fresh()->email)->toBe($ownerEmail);
});

test('an administrator still edits name and phone of another administrator, leaving the email as it is', function () {
    $target = levelsAdministratorTarget();
    $originalEmail = $target->user->email;

    $this->actingAs(User::factory()->administrator()->create())
        ->patch(route('members.update', $target), ['name' => 'Renamed', 'email' => strtoupper($target->user->email), 'phone' => '611111111'])
        ->assertSessionDoesntHaveErrors();

    expect($target->user->fresh()->name)->toBe('Renamed')
        ->and($target->fresh()->phone)->toBe('611111111')
        ->and($target->user->fresh()->email)->toBe($originalEmail);
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
    $owner = User::factory()->member(Household::factory()->create())->owner()->create();
    $ownerAsMember = $owner->member;

    $emails = [$membership->user->email, $administrator->user->email, $owner->email];

    changeEmail($actor, $membership)->assertForbidden();
    changeEmail($actor, $administrator)->assertForbidden();
    changeEmail($actor, $ownerAsMember)->assertForbidden();

    expect([$membership->user->fresh()->email, $administrator->user->fresh()->email, $owner->fresh()->email])->toBe($emails);
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

    $originalEmail = $actor->email;

    changeEmail($actor, $actor->member)->assertForbidden();

    expect($actor->fresh()->email)->toBe($originalEmail);
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
    $originalEmail = $target->user->email;

    changeEmail(User::factory()->owner()->create(), $target, 'TAKEN@Example.com')->assertSessionHasErrors('email');
    changeEmail(User::factory()->administrator()->create(), $target, 'taken@example.com')->assertSessionHasErrors('email');
    changeEmail(levelsMembershipUser(), $target, 'Taken@example.com')->assertSessionHasErrors('email');

    expect($target->user->fresh()->email)->toBe($originalEmail);
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

test('the membership role still edits name and phone of an administrator, leaving the email as it is', function () {
    $target = levelsAdministratorTarget();
    $originalEmail = $target->user->email;

    $this->actingAs(levelsMembershipUser())
        ->patch(route('members.update', $target), ['name' => 'Renamed', 'email' => $originalEmail, 'phone' => '633333333'])
        ->assertSessionDoesntHaveErrors();

    expect($target->user->fresh()->name)->toBe('Renamed')
        ->and($target->fresh()->phone)->toBe('633333333')
        ->and($target->user->fresh()->email)->toBe($originalEmail);
});

test('an inactive administrator target is still protected', function () {
    $target = levelsAdministratorTarget();
    $target->user->administrator->update(['is_active' => false]);
    $originalEmail = $target->user->email;

    changeEmail(levelsMembershipUser(), $target)->assertForbidden();

    expect($target->user->fresh()->email)->toBe($originalEmail);
});

test('the members page tells the membership role which emails it may change', function () {
    $actor = levelsMembershipUser();
    $withRole = levelsMembershipTarget();
    $withoutRole = levelsTarget();

    $this->actingAs($actor)
        ->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => collect($members)->firstWhere('id', $withRole->id)['can_change_email'] === false
                && collect($members)->firstWhere('id', $withoutRole->id)['can_change_email'] === true
                && collect($members)->firstWhere('id', $actor->member->id)['can_change_email'] === false));
});
