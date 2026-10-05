<?php

declare(strict_types=1);

use App\Auth\SecretHasher;
use App\Mail\InvitationMail;
use App\Models\Household;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

function administrator(): User
{
    return User::factory()->administrator()->create();
}

test('the list shows pending and expired invitations, not accepted or cancelled ones', function () {
    $household = Household::factory()->create(['name' => 'Cal Pere']);
    $pending = Invitation::factory()->for($household)->create(['email' => 'pending@example.com']);
    $expired = Invitation::factory()->for($household)->expired()->create(['email' => 'expired@example.com']);
    Invitation::factory()->for($household)->accepted()->create();
    Invitation::factory()->for($household)->cancelled()->create();

    $this->actingAs(administrator())
        ->get(route('households.invitations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('households/Invitations')
            ->has('invitations', 2)
            ->where('invitations.0.email', 'expired@example.com')
            ->where('invitations.0.is_expired', true)
            ->where('invitations.1.id', $pending->id)
            ->where('invitations.1.email', 'pending@example.com')
            ->where('invitations.1.household', 'Cal Pere')
            ->where('invitations.1.is_expired', false)
            ->has('invitations.1.sent_at')
            ->has('invitations.1.expires_at'));

    expect($expired->status()->value)->toBe('expired');
});

test('a user who is not an administrator cannot list, resend or cancel', function () {
    $invitation = Invitation::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('households.invitations.index'))->assertForbidden();
    $this->actingAs($member)->post(route('households.invitations.resend', $invitation))->assertForbidden();
    $this->actingAs($member)->delete(route('households.invitations.cancel', $invitation))->assertForbidden();

    Mail::assertNothingQueued();
    expect($invitation->fresh()->status()->value)->toBe('pending');
});

test('resending gives a new link and 7 more days, and the previous link stops working', function () {
    $this->freezeSecond();
    $household = Household::factory()->create(['name' => 'Cal Pere']);
    $oldToken = 'old-token';
    $invitation = Invitation::factory()->for($household)->create([
        'email' => 'nou@example.com',
        'name' => 'Nou Membre',
        'token_hash' => SecretHasher::hash($oldToken),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs(administrator())
        ->post(route('households.invitations.resend', $invitation))
        ->assertRedirect(route('households.invitations.index'));

    expect($invitation->fresh()->expires_at->equalTo(now()->addDays(7)))->toBeTrue();

    $newToken = null;
    Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail) use (&$newToken) {
        $newToken = basename($mail->url);

        return $mail->hasTo('nou@example.com') && $mail->householdName === 'Cal Pere' && $mail->name === 'Nou Membre';
    });

    auth()->logout();
    $this->get(route('invitations.show', $oldToken))->assertInertia(fn (Assert $page) => $page->component('invitations/Unavailable'));
    $this->get(route('invitations.show', $newToken))->assertInertia(fn (Assert $page) => $page->component('invitations/Accept'));
});

test('resending an expired invitation makes it valid again', function () {
    $invitation = Invitation::factory()->expired()->create();

    $this->actingAs(administrator())->post(route('households.invitations.resend', $invitation));

    expect($invitation->fresh()->status()->value)->toBe('pending');
    Mail::assertQueued(InvitationMail::class);
});

test('accepted and cancelled invitations cannot be resent or cancelled', function () {
    $administrator = administrator();

    foreach ([Invitation::factory()->accepted()->create(), Invitation::factory()->cancelled()->create()] as $invitation) {
        $this->actingAs($administrator)->post(route('households.invitations.resend', $invitation))->assertNotFound();
        $this->actingAs($administrator)->delete(route('households.invitations.cancel', $invitation))->assertNotFound();
    }

    Mail::assertNothingQueued();
});

test('a cancelled invitation shows the unavailable message and cannot be accepted', function () {
    $token = 'secret-token';
    $invitation = Invitation::factory()->create(['token_hash' => SecretHasher::hash($token)]);

    $this->actingAs(administrator())
        ->delete(route('households.invitations.cancel', $invitation))
        ->assertRedirect(route('households.invitations.index'));

    expect($invitation->fresh()->status()->value)->toBe('cancelled');

    auth()->logout();
    $this->get(route('invitations.show', $token))->assertInertia(fn (Assert $page) => $page->component('invitations/Unavailable'));
    $this->post(route('invitations.accept', $token), ['name' => 'Nou', 'password' => 'a-Strong-Passw0rd!', 'password_confirmation' => 'a-Strong-Passw0rd!']);

    expect(User::where('email', $invitation->email)->exists())->toBeFalse();
});

test('inviting an email with a pending invitation leaves the old link unusable', function () {
    $household = Household::factory()->create();
    $oldToken = 'old-token';
    Invitation::factory()->for($household)->create([
        'email' => 'nou@example.com',
        'token_hash' => SecretHasher::hash($oldToken),
    ]);

    $this->actingAs(administrator())->post(route('households.invitations.store', $household), ['email' => 'nou@example.com']);
    auth()->logout();

    $this->get(route('invitations.show', $oldToken))->assertInertia(fn (Assert $page) => $page->component('invitations/Unavailable'));
    $this->actingAs(administrator())->get(route('households.invitations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 1));
});

test('the invitation list text is in Catalan', function () {
    foreach ([
        'Pending invitations',
        'Invitations',
        'Household',
        'Resend',
        'Cancel invitation',
        'Expired',
        'Sent',
        'Expires',
        'Invitation resent.',
        'Invitation cancelled.',
        'No pending invitations.',
    ] as $key) {
        expect(__($key))->not->toBe($key);
    }
});
