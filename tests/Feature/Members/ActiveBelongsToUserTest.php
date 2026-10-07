<?php

declare(strict_types=1);

use App\Actions\ReactivateMember;
use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function userStaff(): User
{
    $user = User::factory()->member(Household::factory()->create())->create();
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($user->member->fresh());

    return $user->fresh();
}

function memberAdministrator(?Household $household = null): User
{
    return User::factory()
        ->member($household ?? Household::factory()->create())
        ->administrator()
        ->create(['password' => 'secret-password-1'])
        ->fresh();
}

test('deactivating a member who is an administrator ends every session of the user', function () {
    $person = memberAdministrator();

    $this->actingAs(User::factory()->administrator()->create())
        ->post(route('members.deactivate', $person->member))
        ->assertRedirect(route('members.index'));

    expect($person->fresh()->canSignIn())->toBeFalse()
        ->and($person->fresh()->hasActiveAdministratorProfile())->toBeFalse()
        ->and($person->fresh()->hasActiveMemberProfile())->toBeFalse();

    $this->actingAs($person->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a deactivated person cannot sign in with a password even with an administrator profile', function () {
    $person = memberAdministrator();
    $person->member->update(['is_active' => false]);

    $this->post(route('login.store'), ['email' => $person->email, 'password' => 'secret-password-1'])
        ->assertSessionHasErrors();
    $this->assertGuest();
});

test('deactivating a household stops its members who are administrators', function () {
    $household = Household::factory()->create();
    $person = memberAdministrator($household);

    $this->actingAs(User::factory()->administrator()->create())
        ->post(route('households.deactivate', $household))
        ->assertRedirect(route('households.index'));

    expect($person->fresh()->canSignIn())->toBeFalse();
    $this->actingAs($person->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
});

test('reactivating the person restores their administrator profile', function () {
    $person = memberAdministrator();
    $person->member->update(['is_active' => false]);

    $this->actingAs(User::factory()->administrator()->create())
        ->post(route('members.reactivate', $person->member))
        ->assertRedirect(route('members.index'));

    expect($person->fresh()->canSignIn())->toBeTrue()
        ->and($person->fresh()->hasActiveAdministratorProfile())->toBeTrue();
});

test('a member without a household can be deactivated and reactivated', function () {
    $member = User::factory()->memberWithoutHousehold()->create()->member->fresh();

    $this->actingAs(userStaff())->post(route('members.deactivate', $member))->assertRedirect(route('members.index'));
    expect($member->fresh()->is_active)->toBeFalse();

    $this->actingAs(userStaff())->post(route('members.reactivate', $member))->assertRedirect(route('members.index'));
    expect($member->fresh()->is_active)->toBeTrue();
});

test('a member of an inactive household still cannot be reactivated', function () {
    $household = Household::factory()->create();
    $member = User::factory()->member($household)->create()->member->fresh();
    $member->update(['is_active' => false]);
    $household->forceFill(['is_active' => false])->save();

    expect(fn () => app(ReactivateMember::class)->handle($member))->toThrow(ValidationException::class);
    expect($member->fresh()->is_active)->toBeFalse();
});

test('the membership role is refused when deactivating a member who is an administrator', function () {
    $person = memberAdministrator();

    $this->actingAs(userStaff())->post(route('members.deactivate', $person->member))->assertForbidden();

    expect($person->member->fresh()->is_active)->toBeTrue();
});

test('the membership role is refused when deactivating a household with a member who is an administrator', function () {
    $household = Household::factory()->create();
    memberAdministrator($household);

    $this->actingAs(userStaff())->post(route('households.deactivate', $household))->assertForbidden();

    expect($household->fresh()->is_active)->toBeTrue();
});

test('an administrator and the owner deactivate a member who is an administrator', function (User $actor) {
    $person = memberAdministrator();

    $this->actingAs($actor)->post(route('members.deactivate', $person->member))->assertRedirect(route('members.index'));

    expect($person->member->fresh()->is_active)->toBeFalse();
})->with([
    'administrator' => fn () => User::factory()->administrator()->create(),
    'owner' => fn () => User::factory()->owner()->create(),
]);

test('the members page hides the deactivate action for an administrator member from the membership role', function () {
    $person = memberAdministrator();
    $plain = User::factory()->member(Household::factory()->create())->create();

    $this->actingAs(userStaff())->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => collect($members)->firstWhere('id', $person->member->id)['can_deactivate'] === false
                && collect($members)->firstWhere('id', $plain->member->id)['can_deactivate'] === true));
});
