<?php

declare(strict_types=1);

use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

function sessionStaff(): User
{
    $user = User::factory()->member(Household::factory()->create())->create();
    $user->member->update(['is_active' => true]);
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($user->member->fresh());

    return $user->fresh();
}

function sessionMember(): User
{
    $user = User::factory()->member(Household::factory()->create())->create();
    $user->member->update(['is_active' => true]);

    return $user->fresh();
}

function recallerFor(User $user): string
{
    return implode('|', [$user->id, $user->remember_token, hash_hmac('sha256', (string) $user->password, (string) $user->password)]);
}

test('a session opened before a deactivation stays refused after the reactivation', function () {
    $user = sessionMember();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $openSession = session()->all();
    $this->flushSession();
    Auth::forgetGuards();

    $this->actingAs(sessionStaff())->post(route('members.deactivate', $user->member));
    $this->actingAs(sessionStaff())->post(route('members.reactivate', $user->member));
    Auth::forgetGuards();

    $this->flushSession();
    $this->withSession($openSession)->get(route('home'));

    $this->assertGuest();
});

test('a session opened after the reactivation keeps working', function () {
    $user = sessionMember();
    $this->actingAs(sessionStaff())->post(route('members.deactivate', $user->member));
    $this->actingAs(sessionStaff())->post(route('members.reactivate', $user->member));
    Auth::forgetGuards();
    $this->flushSession();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $openSession = session()->all();
    $this->flushSession();
    Auth::forgetGuards();

    $this->withSession($openSession)->get(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('the remember cookie of a deactivated and reactivated member is refused', function () {
    $user = sessionMember();
    $cookie = recallerFor($user);

    $this->actingAs(sessionStaff())->post(route('members.deactivate', $user->member));
    $this->actingAs(sessionStaff())->post(route('members.reactivate', $user->member));
    Auth::forgetGuards();
    $this->flushSession();

    $this->withUnencryptedCookie(Auth::guard()->getRecallerName(), $cookie)->get(route('home'));

    $this->assertGuest();
});

test('deactivating a household with its members ends their old sessions too', function () {
    $household = Household::factory()->create();
    $user = User::factory()->member($household)->create();
    $user->member->update(['is_active' => true]);
    $version = $user->session_version;
    $token = $user->remember_token;
    $staff = sessionStaff();

    $this->actingAs($staff)->post(route('households.deactivate', $household), ['with_members' => true]);

    $fresh = $user->fresh();
    expect($fresh->member->is_active)->toBeFalse()
        ->and($fresh->session_version)->toBeGreaterThan($version)
        ->and($fresh->remember_token)->not->toBe($token);
});

test('deactivating a household alone leaves its members sessions alone', function () {
    $household = Household::factory()->create();
    $user = User::factory()->member($household)->create();
    $user->member->update(['is_active' => true]);
    $version = $user->session_version;

    $this->actingAs(sessionStaff())->post(route('households.deactivate', $household));

    expect($user->fresh()->session_version)->toBe($version);
});
