<?php

use App\Actions\RequestEmailChange;
use App\Mail\EmailChangeMail;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

/**
 * Ask for an email change as the given user and return the link from the
 * queued email.
 */
function requestEmailChange(User $user, string $email): string
{
    test()->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => $email])
        ->assertSessionHasNoErrors();

    $url = null;

    Mail::assertQueued(EmailChangeMail::class, function (EmailChangeMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    return $url;
}

test('the profile page shows the name, phone and pending email', function () {
    $user = User::factory()->member(Household::factory()->create())->create();
    $user->member->update(['phone' => '600 111 222']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('phone', '600 111 222')
            ->where('pendingEmail', null));
});

test('a member updates their name and phone', function () {
    $user = User::factory()->member(Household::factory()->create())->create(['email' => 'anna@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Anna Puig',
            'phone' => '600 111 222',
            'email' => 'anna@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->name)->toBe('Anna Puig')
        ->and($user->member->phone)->toBe('600 111 222');
    Mail::assertNothingQueued();
});

test('the phone is optional and can be cleared', function () {
    $user = User::factory()->member(Household::factory()->create())->create();
    $user->member->update(['phone' => '600 111 222']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'phone' => '', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($user->member->refresh()->phone)->toBeNull();
});

test('a user without a member profile gets no phone field', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('hasMember', false)
            ->missing('phone'));
});

test('a user with a member profile gets the phone field', function () {
    $this->actingAs(User::factory()->member(Household::factory()->create())->create())
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('hasMember', true)->has('phone'));
});

test('a phone is rejected for a user without a member profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Nou', 'phone' => '600 111 222', 'email' => $user->email])
        ->assertSessionHasErrors('phone');

    expect($user->refresh()->name)->not->toBe('Nou');
});

test('the name is required', function () {
    $this->actingAs(User::factory()->create())
        ->patch(route('profile.update'), ['name' => '', 'email' => 'x@example.com'])
        ->assertSessionHasErrors('name');
});

test('requesting an email change queues a confirmation to the new address and keeps the current one', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'New@Example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    Mail::assertQueued(EmailChangeMail::class, fn (EmailChangeMail $mail) => $mail->hasTo('new@example.com'));
    expect($user->refresh()->email)->toBe('old@example.com')
        ->and($user->pending_email)->toBe('new@example.com');

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('pendingEmail', 'new@example.com'));
});

test('the old email keeps working for sign-in until the link is opened', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    requestEmailChange($user, 'new@example.com');
    auth()->logout();

    $this->post(route('login.store'), ['email' => 'old@example.com', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('opening the confirmation link only shows a page and changes nothing', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $url = requestEmailChange($user, 'new@example.com');

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/EmailChangeConfirm'));

    expect($user->refresh()->email)->toBe('old@example.com')
        ->and($user->pending_email)->toBe('new@example.com');

    $this->post($url)->assertRedirect(route('profile.edit'));
    expect($user->refresh()->email)->toBe('new@example.com');
});

test('confirming the change updates the email', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $url = requestEmailChange($user, 'new@example.com');

    $this->actingAs($user)->post($url)->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->email)->toBe('new@example.com')
        ->and($user->pending_email)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('the confirmation works from a browser that is not signed in', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $url = requestEmailChange($user, 'new@example.com');
    auth()->logout();

    $this->post($url)->assertRedirect();

    expect($user->refresh()->email)->toBe('new@example.com');
});

test('the confirmation cannot be used twice', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $url = requestEmailChange($user, 'new@example.com');

    $this->post($url);
    $user->update(['email' => 'other@example.com']);

    $this->post($url);

    expect($user->refresh()->email)->toBe('other@example.com');
});

test('the confirmation expires', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $url = requestEmailChange($user, 'new@example.com');

    $this->travel(RequestEmailChange::HOURS_VALID + 1)->hours();
    $this->post($url)->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe('old@example.com');
});

test('the second of two users confirming the same new email gets the in-use message and the first keeps it', function () {
    $first = User::factory()->create(['email' => 'first@example.com']);
    $second = User::factory()->create(['email' => 'second@example.com']);
    $firstUrl = requestEmailChange($first, 'new@example.com');
    Mail::fake();
    $secondUrl = requestEmailChange($second, 'New@example.com');

    $this->post($firstUrl);
    $this->post($secondUrl)
        ->assertRedirect(route('profile.edit'))
        ->assertInertiaFlash('toast.message', 'Aquesta adreça electrònica ja està en ús.');

    expect($first->refresh()->email)->toBe('new@example.com')
        ->and($second->refresh()->email)->toBe('second@example.com')
        ->and($second->pending_email)->toBeNull()
        ->and($second->pending_email_token_hash)->toBeNull();
});

test('losing the race for the new email at save time shows the in-use message instead of failing', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $url = requestEmailChange($user, 'new@example.com');

    // The other user grabs the address between the availability check and the save.
    User::updating(function () {
        User::factory()->create(['email' => 'new@example.com']);
    });

    $response = $this->post($url);
    // Removed before any assertion, so a failure cannot leak the listener into other tests.
    User::flushEventListeners();

    $response->assertRedirect(route('profile.edit'))
        ->assertInertiaFlash('toast.message', 'Aquesta adreça electrònica ja està en ús.');
    expect($user->refresh()->email)->toBe('old@example.com')
        ->and($user->pending_email)->toBeNull();
});

test('a used or expired link shows the error instead of the button', function () {
    $this->get(route('email-change.show', ['token' => 'nope']))
        ->assertRedirect(route('profile.edit'));
});

test('a new request replaces the previous link', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $first = requestEmailChange($user, 'first@example.com');
    Mail::fake();
    $second = requestEmailChange($user, 'second@example.com');

    $this->post($first);
    expect($user->refresh()->email)->toBe('old@example.com');

    $this->post($second);
    expect($user->refresh()->email)->toBe('second@example.com');
});

test('an unknown token changes nothing', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    requestEmailChange($user, 'new@example.com');

    $this->post(route('email-change.confirm', ['token' => 'nope']));

    expect($user->refresh()->email)->toBe('old@example.com');
});

test('an email used by another user is rejected, ignoring case', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'TAKEN@example.com'])
        ->assertSessionHasErrors('email');

    Mail::assertNothingQueued();
});

test('an email taken after the request cannot be confirmed', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $url = requestEmailChange($user, 'new@example.com');
    User::factory()->create(['email' => 'new@example.com']);

    $this->post($url);

    expect($user->refresh()->email)->toBe('old@example.com');
});

test('editing the profile never touches another member', function () {
    $household = Household::factory()->create();
    $anna = User::factory()->member($household)->create(['name' => 'Anna']);
    $pere = User::factory()->member($household)->create(['name' => 'Pere', 'email' => 'pere@example.com']);

    $this->actingAs($anna)
        ->patch(route('profile.update'), [
            'name' => 'Hacked',
            'email' => 'anna@example.com',
            'phone' => '999',
            'user_id' => $pere->id,
            'id' => $pere->id,
        ]);

    expect($pere->refresh()->name)->toBe('Pere')
        ->and($pere->member->phone)->toBeNull()
        ->and($anna->refresh()->name)->toBe('Hacked');
});

test('guests cannot edit a profile', function () {
    $this->patch(route('profile.update'), ['name' => 'X', 'email' => 'x@example.com'])
        ->assertRedirect(route('login'));
});
