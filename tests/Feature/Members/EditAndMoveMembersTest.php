<?php

declare(strict_types=1);

use App\Auth\Role;
use App\Auth\SecretHasher;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function membershipUser(): User
{
    $user = User::factory()->member(Household::factory()->create())->create();
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($user->member->fresh());

    return $user->fresh();
}

function memberIn(Household $household, array $user = []): Member
{
    $member = User::factory()->member($household)->create($user)->member->fresh();
    $member->update(['is_active' => true]);

    return $member;
}

test('a member with the membership role edits name, email and phone of another member', function () {
    $member = memberIn(Household::factory()->create(), ['email' => 'old@example.com']);

    $this->actingAs(membershipUser())
        ->patch(route('members.update', $member), ['name' => 'Maria Nova', 'email' => 'New@Example.com', 'phone' => '600123123'])
        ->assertRedirect(route('members.index'));

    $member->refresh();
    expect($member->user->name)->toBe('Maria Nova')
        ->and($member->user->email)->toBe('new@example.com')
        ->and($member->phone)->toBe('600123123');
});

test('the new email works for sign-in at once, with no confirmation', function () {
    $member = memberIn(Household::factory()->create(), ['email' => 'old@example.com']);

    $this->actingAs(membershipUser())
        ->patch(route('members.update', $member), ['name' => $member->user->name, 'email' => 'new@example.com']);

    expect(User::where('email', 'new@example.com')->first()?->is($member->user))->toBeTrue()
        ->and($member->user->fresh()->pending_email)->toBeNull();
});

test('the email also changes for the administrator profile of the same user', function () {
    $member = memberIn(Household::factory()->create(), ['email' => 'old@example.com']);
    $member->user->administrator()->create(['is_active' => true]);

    $this->actingAs(User::factory()->owner()->create())
        ->patch(route('members.update', $member), ['name' => 'X', 'email' => 'new@example.com']);

    expect($member->user->administrator->fresh()->user->email)->toBe('new@example.com');
});

test('an email used by another user is rejected, ignoring case', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $member = memberIn(Household::factory()->create(), ['email' => 'old@example.com']);

    $this->actingAs(membershipUser())
        ->patch(route('members.update', $member), ['name' => 'X', 'email' => 'TAKEN@example.com'])
        ->assertSessionHasErrors('email');

    expect($member->user->fresh()->email)->toBe('old@example.com');
});

test('keeping the member own email is accepted', function () {
    $member = memberIn(Household::factory()->create(), ['email' => 'old@example.com']);

    $this->actingAs(membershipUser())
        ->patch(route('members.update', $member), ['name' => 'Nou', 'email' => 'old@example.com'])
        ->assertSessionDoesntHaveErrors();

    expect($member->user->fresh()->name)->toBe('Nou');
});

test('moving a member changes only their household', function () {
    $from = Household::factory()->create();
    $to = Household::factory()->create();
    $member = memberIn($from);

    $this->actingAs(membershipUser())
        ->patch(route('members.household.update', $member), ['household_id' => $to->id])
        ->assertRedirect(route('members.index'));

    expect($member->fresh()->household_id)->toBe($to->id)
        ->and($from->members()->count())->toBe(0)
        ->and($to->members()->pluck('id')->all())->toBe([$member->id]);
});

test('a member cannot be moved to an inactive or unknown household', function () {
    $member = memberIn(Household::factory()->create());
    $user = membershipUser();

    $this->actingAs($user)
        ->patch(route('members.household.update', $member), ['household_id' => Household::factory()->inactive()->create()->id])
        ->assertSessionHasErrors('household_id');
    $this->actingAs($user)
        ->patch(route('members.household.update', $member), ['household_id' => fake()->uuid()])
        ->assertSessionHasErrors('household_id');
});

test('a member without the role cannot edit or move members, or list them', function () {
    $member = memberIn(Household::factory()->create());
    $other = Household::factory()->create();
    $plain = User::factory()->member(Household::factory()->create())->create();

    $this->actingAs($plain)->get(route('members.index'))->assertForbidden();
    $this->actingAs($plain)->patch(route('members.update', $member), ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
    $this->actingAs($plain)->patch(route('members.household.update', $member), ['household_id' => $other->id])->assertForbidden();
    expect($member->fresh()->household_id)->not->toBe($other->id);
});

test('administrators edit and move members too', function () {
    $member = memberIn(Household::factory()->create(), ['email' => 'old@example.com']);
    $to = Household::factory()->create();
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)
        ->patch(route('members.update', $member), ['name' => 'Admin Edit', 'email' => 'by-admin@example.com'])
        ->assertRedirect(route('members.index'));
    $this->actingAs($administrator)
        ->patch(route('members.household.update', $member), ['household_id' => $to->id])
        ->assertRedirect(route('members.index'));

    expect($member->user->fresh()->email)->toBe('by-admin@example.com')
        ->and($member->fresh()->household_id)->toBe($to->id);
});

test('the members page lists members with their household and the active households to move to', function () {
    $household = Household::factory()->create(['name' => 'Cal Pere']);
    Household::factory()->inactive()->create();
    memberIn($household, ['name' => 'Pere']);

    $this->actingAs(membershipUser())->get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/Index')
            ->where('members', fn ($members) => collect($members)->contains(fn ($row) => $row['name'] === 'Pere' && $row['household_id'] === $household->id))
            ->has('households', 2));
});

test('the members page sorts members by name ignoring accents and case', function () {
    $household = Household::factory()->create();
    foreach (['Zoe', 'Ferran', 'Èric', 'emma', 'Àlex'] as $name) {
        memberIn($household, ['name' => $name]);
    }
    $names = ['Àlex', 'emma', 'Èric', 'Ferran', 'Zoe'];

    $this->actingAs(membershipUser())->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => collect($members)->pluck('name')->intersect($names)->values()->all() === $names));
});

test('an immediate email change drops a pending self-service change, so its old link cannot overwrite it', function () {
    $member = memberIn(Household::factory()->create(), ['email' => 'old@example.com']);
    $member->user->forceFill([
        'pending_email' => 'requested@example.com',
        'pending_email_token_hash' => SecretHasher::hash('old-link-token'),
        'pending_email_expires_at' => now()->addHour(),
    ])->save();

    $this->actingAs(membershipUser())
        ->patch(route('members.update', $member), ['name' => 'X', 'email' => 'set-by-team@example.com']);

    $this->post(route('email-change.confirm', ['token' => 'old-link-token']));

    $user = $member->user->fresh();
    expect($user->email)->toBe('set-by-team@example.com')
        ->and($user->pending_email)->toBeNull()
        ->and($user->pending_email_token_hash)->toBeNull()
        ->and($user->pending_email_expires_at)->toBeNull();
});

test('a failing member update rolls back the user update', function () {
    $member = memberIn(Household::factory()->create(), ['name' => 'Abans', 'email' => 'old@example.com']);
    Member::updating(fn () => throw new RuntimeException('boom'));

    $this->withoutExceptionHandling();

    try {
        $this->actingAs(membershipUser())
            ->patch(route('members.update', $member), ['name' => 'Despres', 'email' => 'new@example.com', 'phone' => '600123123']);
    } catch (RuntimeException) {
    }

    expect($member->user->fresh()->name)->toBe('Abans')
        ->and($member->user->fresh()->email)->toBe('old@example.com');
});
