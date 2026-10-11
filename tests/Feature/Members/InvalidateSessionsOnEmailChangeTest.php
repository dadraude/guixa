<?php

declare(strict_types=1);

use App\Auth\MagicLinkBroker;
use App\Auth\Role;
use App\Mail\MagicLinkMail;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

function emailChangeStaff(): User
{
    $user = User::factory()->member(Household::factory()->create())->create();
    $user->member->update(['is_active' => true]);
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($user->member->fresh());

    return $user->fresh();
}

function emailChangeTarget(): Member
{
    $member = User::factory()->member(Household::factory()->create())->create()->member->fresh();
    $member->update(['is_active' => true]);

    return $member;
}

function editMember(Member $member, string $email): void
{
    test()->actingAs(emailChangeStaff())
        ->patch(route('members.update', $member), ['name' => 'Same Name', 'email' => $email, 'phone' => '600000000'])
        ->assertSessionDoesntHaveErrors();
    Auth::forgetGuards();
    test()->flushSession();
}

test('a magic link and code requested before someone else changes the email stop working', function () {
    Mail::fake();
    $member = emailChangeTarget();
    $this->withSession([])->post(route('magic-link.store'), ['email' => $member->user->email]);
    $url = null;
    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    editMember($member, 'new@example.com');

    $this->post($url);
    $this->assertGuest();
    expect($member->user->magicLinks()->whereNull('used_at')->count())->toBe(0);
});

test('the old remember cookie no longer signs in after someone else changes the email', function () {
    $member = emailChangeTarget();
    $user = $member->user->fresh();
    $cookie = implode('|', [$user->id, $user->remember_token, Auth::guard()->hashPasswordForCookie($user->password)]);

    editMember($member, 'new@example.com');

    $this->withCookie(Auth::guard()->getRecallerName(), $cookie)->get(route('home'));

    $this->assertGuest();
});

test('an open session is signed out on its next request after someone else changes the email', function () {
    $member = emailChangeTarget();
    $this->post(route('login.store'), ['email' => $member->user->email, 'password' => 'password']);
    $openSession = session()->all();
    $this->flushSession();
    Auth::forgetGuards();

    editMember($member, 'new@example.com');

    $this->withSession($openSession)->get(route('home'));

    $this->assertGuest();
});

test('saving a member without changing the email invalidates nothing', function () {
    $member = emailChangeTarget();
    $user = $member->user->fresh();
    $version = $user->session_version;
    $token = $user->remember_token;
    $broker = app(MagicLinkBroker::class);
    Mail::fake();
    $broker->send($user);

    editMember($member, strtoupper($user->email));

    $fresh = $user->fresh();
    expect($fresh->session_version)->toBe($version)
        ->and($fresh->remember_token)->toBe($token)
        ->and($fresh->magicLinks()->whereNull('used_at')->count())->toBe(1);
});
