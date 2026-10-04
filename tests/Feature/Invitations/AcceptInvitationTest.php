<?php

use App\Mail\InvitationMail;
use App\Models\Household;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: Invitation, 1: string} the invitation and the secret from its link
 */
function sentInvitation(array $attributes = []): array
{
    Mail::fake();

    $household = Household::factory()->create();
    test()->actingAs(User::factory()->administrator()->create())
        ->post(route('households.invitations.store', $household), ['email' => 'nou@example.com', ...$attributes]);
    auth()->logout();

    $token = null;
    Mail::assertQueued(InvitationMail::class, function ($mail) use (&$token) {
        $token = basename($mail->url);

        return true;
    });

    return [Invitation::sole(), $token];
}

test('opening the invitation shows the form with the given name and does not use it up', function () {
    [, $token] = sentInvitation(['name' => 'Nou Membre']);

    $this->get(route('invitations.show', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invitations/Accept')
            ->where('name', 'Nou Membre')
            ->where('email', 'nou@example.com'));
    $this->get(route('invitations.show', $token))->assertOk();

    expect(Invitation::sole()->status()->value)->toBe('pending');
    $this->assertGuest();
});

test('accepting creates the member in the invitation household and signs them in', function () {
    [$invitation, $token] = sentInvitation();

    $this->post(route('invitations.accept', $token), [
        'name' => 'Nou Membre',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('home'));

    $user = User::where('email', 'nou@example.com')->sole();
    expect($user->name)->toBe('Nou Membre')
        ->and($user->member->household_id)->toBe($invitation->household_id)
        ->and($user->member->is_active)->toBeTrue()
        ->and($user->member->phone)->toBeNull()
        ->and($user->password)->not->toBeNull()
        ->and($invitation->fresh()->status()->value)->toBe('accepted');
    $this->assertAuthenticatedAs($user);
});

test('the person can change the name given when inviting', function () {
    [, $token] = sentInvitation(['name' => 'Nou Membre']);

    $this->post(route('invitations.accept', $token), [
        'name' => 'Nom Correcte',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'nou@example.com')->sole()->name)->toBe('Nom Correcte');
});

test('the name and a confirmed password are required', function () {
    [, $token] = sentInvitation();

    $this->post(route('invitations.accept', $token), ['name' => '', 'password' => 'password', 'password_confirmation' => 'other'])
        ->assertSessionHasErrors(['name', 'password']);

    expect(User::where('email', 'nou@example.com')->exists())->toBeFalse()
        ->and(Invitation::sole()->status()->value)->toBe('pending');
});

test('an invitation cannot be used twice', function () {
    [, $token] = sentInvitation();
    $data = ['name' => 'Nou', 'password' => 'password', 'password_confirmation' => 'password'];

    $this->post(route('invitations.accept', $token), $data);
    auth()->logout();

    $this->post(route('invitations.accept', $token), $data)->assertRedirect(route('invitations.show', $token));
    $this->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page->component('invitations/Unavailable'));

    $this->assertGuest();
    expect(User::where('email', 'nou@example.com')->count())->toBe(1);
});

test('an invitation cannot be used after 7 days', function () {
    [, $token] = sentInvitation();

    $this->travel(7)->days();
    $this->travel(1)->seconds();

    $this->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page->component('invitations/Unavailable'));
    $this->post(route('invitations.accept', $token), ['name' => 'Nou', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertRedirect(route('invitations.show', $token));

    $this->assertGuest();
    expect(User::where('email', 'nou@example.com')->exists())->toBeFalse();
});

test('an invitation still works just before it expires', function () {
    [, $token] = sentInvitation();

    $this->travel(7)->days();
    $this->travel(-1)->seconds();

    $this->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page->component('invitations/Accept'));
});

test('a cancelled invitation shows the unavailable message', function () {
    [$invitation, $token] = sentInvitation();
    $invitation->forceFill(['cancelled_at' => now()])->save();

    $this->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page->component('invitations/Unavailable'));
});

test('an unknown invitation shows the unavailable message', function () {
    $this->get(route('invitations.show', str_repeat('a', 64)))
        ->assertInertia(fn (Assert $page) => $page->component('invitations/Unavailable'));
});

test('an invitation for an email that already has a user is refused and stays pending', function () {
    [$invitation, $token] = sentInvitation();
    User::factory()->administrator()->create(['email' => 'nou@example.com']);

    $this->post(route('invitations.accept', $token), ['name' => 'Nou', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($invitation->fresh()->status()->value)->toBe('pending')
        ->and(User::where('email', 'nou@example.com')->count())->toBe(1);
});
