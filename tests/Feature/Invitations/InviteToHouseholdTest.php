<?php

use App\Mail\InvitationMail;
use App\Models\Household;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

test('an administrator invites an email to a household and the email is queued', function () {
    $this->freezeSecond();
    $administrator = User::factory()->administrator()->create();
    $household = Household::factory()->create();

    $this->actingAs($administrator)
        ->post(route('households.invitations.store', $household), ['email' => 'Nou@Example.com', 'name' => 'Nou Membre'])
        ->assertRedirect(route('households.index'));

    $invitation = Invitation::sole();
    expect($invitation->household_id)->toBe($household->id)
        ->and($invitation->email)->toBe('nou@example.com')
        ->and($invitation->name)->toBe('Nou Membre')
        ->and($invitation->expires_at->equalTo(now()->addDays(7)))->toBeTrue();

    Mail::assertQueued(InvitationMail::class, fn (InvitationMail $mail) => $mail->hasTo('nou@example.com'));
});

test('the invitation token is stored only as a hash', function () {
    $administrator = User::factory()->administrator()->create();
    $household = Household::factory()->create();

    $this->actingAs($administrator)->post(route('households.invitations.store', $household), ['email' => 'nou@example.com']);

    Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail) {
        $token = basename($mail->url);

        expect(Invitation::sole()->token_hash)->not->toBe($token)->and(strlen($token))->toBe(64);

        return true;
    });
});

test('the name is optional', function () {
    $administrator = User::factory()->administrator()->create();
    $household = Household::factory()->create();

    $this->actingAs($administrator)
        ->post(route('households.invitations.store', $household), ['email' => 'nou@example.com'])
        ->assertSessionHasNoErrors();

    expect(Invitation::sole()->name)->toBeNull();
});

test('a user who is not an administrator cannot invite', function () {
    $household = Household::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('households.invitations.store', $household), ['email' => 'nou@example.com'])
        ->assertForbidden();

    expect(Invitation::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('an email that already belongs to a member is rejected, ignoring case', function () {
    $administrator = User::factory()->administrator()->create();
    $household = Household::factory()->create();
    User::factory()->member($household)->create(['email' => 'anna@example.com']);

    $this->actingAs($administrator)
        ->post(route('households.invitations.store', $household), ['email' => 'ANNA@Example.com'])
        ->assertSessionHasErrors('email');

    expect(Invitation::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('inviting an email again replaces the pending invitation', function () {
    $administrator = User::factory()->administrator()->create();
    $household = Household::factory()->create();

    $this->actingAs($administrator)->post(route('households.invitations.store', $household), ['email' => 'nou@example.com']);
    $first = Invitation::sole();
    $this->actingAs($administrator)->post(route('households.invitations.store', $household), ['email' => 'nou@example.com']);

    expect(Invitation::count())->toBe(2)
        ->and($first->fresh()->status()->value)->toBe('cancelled')
        ->and(Invitation::whereKeyNot($first->id)->sole()->status()->value)->toBe('pending');
});

test('the email is required and valid', function () {
    $administrator = User::factory()->administrator()->create();
    $household = Household::factory()->create();

    $this->actingAs($administrator)
        ->post(route('households.invitations.store', $household), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});

test('invitations have a UUIDv7 key and expired is derived from the expiry', function () {
    $invitation = Invitation::factory()->create();

    expect(Str::isUuid($invitation->id, 7))->toBeTrue()
        ->and($invitation->status()->value)->toBe('pending');

    $this->travel(7)->days();
    $this->travel(1)->seconds();

    expect($invitation->fresh()->status()->value)->toBe('expired');
});

test('the invitation screen and email text are in Catalan', function () {
    foreach ([
        'Invite',
        'Invitation to join :app',
        'Accept invitation',
        'This invitation is no longer valid',
        'Ask the membership commission for a new one.',
        'Your household',
        'Members',
    ] as $key) {
        expect(__($key))->not->toBe($key);
    }
});
