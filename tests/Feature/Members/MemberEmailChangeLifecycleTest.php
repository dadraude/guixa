<?php

declare(strict_types=1);

use App\Auth\SecretHasher;
use App\Models\Household;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function memberWithPendingEmailChange(Household $household, string $token): User
{
    $user = User::factory()->member($household)->create(['email' => 'old-'.$token.'@example.com']);
    $user->forceFill([
        'pending_email' => 'new-'.$token.'@example.com',
        'pending_email_token_hash' => SecretHasher::hash($token),
        'pending_email_expires_at' => now()->addHour(),
    ])->save();

    return $user;
}

test('deactivating one member cancels their pending email change', function () {
    $user = memberWithPendingEmailChange(Household::factory()->create(), 'one-member');

    $this->actingAs(membershipRoleUser())->post(route('members.deactivate', $user->member))->assertRedirect();

    $this->get(route('email-change.show', ['token' => 'one-member']))
        ->assertInertia(fn (Assert $page) => $page->component('auth/EmailChangeUnavailable'));
    expect($user->fresh()->email)->toBe('old-one-member@example.com')
        ->and($user->fresh()->pending_email)->toBeNull();
});

test('deactivating a household with its members cancels their pending email changes', function () {
    $household = Household::factory()->create();
    $user = memberWithPendingEmailChange($household, 'whole-household');

    $this->actingAs(membershipRoleUser())
        ->post(route('households.deactivate', $household), ['with_members' => true])
        ->assertRedirect();

    expect($user->member->fresh()->is_active)->toBeFalse();
    $this->get(route('email-change.show', ['token' => 'whole-household']))
        ->assertInertia(fn (Assert $page) => $page->component('auth/EmailChangeUnavailable'));
    expect($user->fresh()->pending_email)->toBeNull();
});

test('an inactive member cannot confirm an otherwise valid email change', function () {
    $user = memberWithPendingEmailChange(Household::factory()->create(), 'inactive-link');
    $user->member->update(['is_active' => false]);

    $this->get(route('email-change.show', ['token' => 'inactive-link']))
        ->assertInertia(fn (Assert $page) => $page->component('auth/EmailChangeUnavailable'));
    $this->post(route('email-change.confirm', ['token' => 'inactive-link']))
        ->assertRedirect(route('email-change.show', ['token' => 'inactive-link']));

    expect($user->fresh()->email)->toBe('old-inactive-link@example.com');
});

test('confirming an own email change revokes old magic links without ending sessions', function () {
    $user = memberWithPendingEmailChange(Household::factory()->create(), 'own-change');
    $user->forceFill(['remember_token' => 'remember-this-device'])->save();
    $user->magicLinks()->create([
        'token_hash' => SecretHasher::hash('old-magic-link'),
        'expires_at' => now()->addMinutes(15),
    ]);
    $version = $user->session_version;

    $this->actingAs($user)->post(route('email-change.confirm', ['token' => 'own-change']))->assertRedirect();
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->email)->toBe('new-own-change@example.com')
        ->and($user->fresh()->session_version)->toBe($version)
        ->and($user->fresh()->remember_token)->toBe('remember-this-device');

    auth()->logout();
    $this->post(route('magic-link.consume', ['token' => 'old-magic-link']))->assertRedirect(route('login'));
    $this->assertGuest();
});
