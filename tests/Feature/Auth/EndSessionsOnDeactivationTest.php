<?php

declare(strict_types=1);

use App\Auth\MagicLinkBroker;
use App\Auth\Role;
use App\Mail\MagicLinkMail;
use App\Models\Commission;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

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
    return implode('|', [$user->id, $user->remember_token, Auth::guard()->hashPasswordForCookie($user->password)]);
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

function deactivateAndReactivate(User $user): void
{
    test()->actingAs(sessionStaff())->post(route('members.deactivate', $user->member));
    test()->actingAs(sessionStaff())->post(route('members.reactivate', $user->member));
    Auth::forgetGuards();
    test()->flushSession();
}

test('a valid remember cookie signs in a member who was not deactivated', function () {
    $user = sessionMember();

    $this->withCookie(Auth::guard()->getRecallerName(), recallerFor($user))->get(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('the remember cookie of a deactivated and reactivated member is refused', function () {
    $user = sessionMember();
    $cookie = recallerFor($user);

    deactivateAndReactivate($user);

    $this->withCookie(Auth::guard()->getRecallerName(), $cookie)->get(route('home'));

    $this->assertGuest();
});

test('a stale session signs out only its own device, keeping the new remember cookie valid', function () {
    $user = sessionMember();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $oldSession = session()->all();
    $this->flushSession();
    Auth::forgetGuards();

    deactivateAndReactivate($user);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => true]);
    $newSession = session()->all();
    $this->flushSession();
    Auth::forgetGuards();
    $tokenBefore = $user->fresh()->remember_token;
    $cookie = recallerFor($user->fresh());

    $this->withSession($oldSession)->get(route('home'));
    Auth::forgetGuards();

    expect($user->fresh()->remember_token)->toBe($tokenBefore);

    $this->flushSession();
    $this->withCookie(Auth::guard()->getRecallerName(), $cookie)->get(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('a magic link sign-in after a reactivation keeps working', function () {
    Mail::fake();
    $user = sessionMember();
    deactivateAndReactivate($user);
    app(MagicLinkBroker::class)->send($user);
    $url = null;
    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    $this->post($url);
    $this->get(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('a fresh remember cookie after a reactivation keeps working across requests', function () {
    $user = sessionMember();
    deactivateAndReactivate($user);
    $cookie = recallerFor($user->fresh());

    $this->withCookie(Auth::guard()->getRecallerName(), $cookie)->get(route('home'));
    $this->get(route('home'));

    $this->assertAuthenticatedAs($user);
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
