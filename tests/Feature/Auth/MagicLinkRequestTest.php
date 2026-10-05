<?php

declare(strict_types=1);

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

/**
 * Ask for a magic link from the sign-in screen and return the queued email.
 */
function requestMagicLink(string $email = 'marta@example.com'): ?MagicLinkMail
{
    test()->post(route('magic-link.store'), ['email' => $email])
        ->assertRedirect(route('magic-link.code'));

    return Mail::queued(MagicLinkMail::class)->last();
}

test('the sign-in screen offers a magic link and a password', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));

    expect(__('Email me a link to log in'))->toBe("Envia'm un enllaç per iniciar la sessió");
});

test('requesting a link emails the person a link and a 6-digit code', function () {
    User::factory()->create(['email' => 'marta@example.com']);

    $mail = requestMagicLink('Marta@Example.com');

    expect($mail)->not->toBeNull()
        ->and($mail->hasTo('marta@example.com'))->toBeTrue()
        ->and($mail->code)->toMatch('/^\d{6}$/');

    $mail->assertSeeInHtml($mail->code);
    $mail->assertSeeInHtml('codi');
});

test('the response is identical for an email that does not exist', function () {
    User::factory()->create(['email' => 'marta@example.com']);

    $known = $this->post(route('magic-link.store'), ['email' => 'marta@example.com']);
    $knownPage = $this->get(route('magic-link.code'));
    Mail::assertQueuedCount(1);

    $this->flushSession();

    $unknown = $this->post(route('magic-link.store'), ['email' => 'nobody@example.com']);
    $unknownPage = $this->get(route('magic-link.code'));
    Mail::assertQueuedCount(1);

    expect($unknown->headers->get('Location'))->toBe($known->headers->get('Location'))
        ->and($unknown->getSession()->get('status'))->toBe($known->getSession()->get('status'));

    $unknownPage->assertInertia(fn (Assert $page) => $page
        ->component('auth/MagicLinkCode')
        ->where('email', 'nobody@example.com'));
    $knownPage->assertInertia(fn (Assert $page) => $page
        ->component('auth/MagicLinkCode')
        ->where('email', 'marta@example.com'));
});

test('the code page needs a link request first', function () {
    $this->get(route('magic-link.code'))->assertRedirect(route('login'));
});

test('the correct code signs in the session that asked for it', function () {
    $user = User::factory()->create(['email' => 'marta@example.com']);
    $mail = requestMagicLink();

    $this->post(route('magic-link.code.store'), ['code' => $mail->code])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('the code is refused in a different session', function () {
    User::factory()->create(['email' => 'marta@example.com']);
    $mail = requestMagicLink();

    $this->flushSession();

    $this->post(route('magic-link.code.store'), ['code' => $mail->code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a wrong code is refused', function () {
    User::factory()->create(['email' => 'marta@example.com']);
    $mail = requestMagicLink();
    $this->post(route('magic-link.code.store'), ['code' => wrongCode($mail)])
        ->assertSessionHasErrors(['code' => __('The code is not valid or has expired.')]);

    $this->assertGuest();
});

test('the code expires after 15 minutes', function () {
    User::factory()->create(['email' => 'marta@example.com']);
    $mail = requestMagicLink();

    $this->travel(15)->minutes();
    $this->travel(1)->seconds();

    $this->post(route('magic-link.code.store'), ['code' => $mail->code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('the code works only once', function () {
    User::factory()->create(['email' => 'marta@example.com']);
    $mail = requestMagicLink();

    $this->post(route('magic-link.code.store'), ['code' => $mail->code]);
    auth()->logout();

    $this->post(route('magic-link.code.store'), ['code' => $mail->code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('using the link consumes the code', function () {
    User::factory()->create(['email' => 'marta@example.com']);
    $mail = requestMagicLink();

    $this->post($mail->url)->assertRedirect(route('home'));
    auth()->logout();

    $this->post(route('magic-link.code.store'), ['code' => $mail->code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('using the code consumes the link', function () {
    User::factory()->create(['email' => 'marta@example.com']);
    $mail = requestMagicLink();

    $this->post(route('magic-link.code.store'), ['code' => $mail->code]);
    auth()->logout();

    $this->post($mail->url)->assertRedirect(route('login'));

    $this->assertGuest();
});

test('the link opened on another device signs that device in', function () {
    $user = User::factory()->create(['email' => 'marta@example.com']);
    $mail = requestMagicLink();

    $this->flushSession();

    $this->post($mail->url)->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('a newer request invalidates the older link and code', function () {
    User::factory()->create(['email' => 'marta@example.com']);
    $first = requestMagicLink();
    $second = requestMagicLink();

    $this->post(route('magic-link.code.store'), ['code' => $first->code === $second->code ? 'x' : $first->code])
        ->assertSessionHasErrors('code');
    $this->post($first->url)->assertRedirect(route('login'));
    $this->assertGuest();

    $this->post($second->url)->assertRedirect(route('home'));
    $this->assertAuthenticated();
});

test('repeated link requests are throttled', function () {
    User::factory()->create(['email' => 'marta@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('magic-link.store'), ['email' => 'marta@example.com'])
            ->assertRedirect(route('magic-link.code'));
    }

    $this->post(route('magic-link.store'), ['email' => 'marta@example.com'])
        ->assertTooManyRequests();
});

/**
 * A 6-digit code that is surely not the one in this email.
 */
function wrongCode(MagicLinkMail $mail): string
{
    return str_pad((string) (((int) $mail->code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
}

test('repeated wrong codes are throttled', function () {
    User::factory()->create(['email' => 'marta@example.com']);
    $mail = requestMagicLink();

    foreach (range(1, 4) as $attempt) {
        $this->post(route('magic-link.code.store'), ['code' => wrongCode($mail)]);
    }

    $this->post(route('magic-link.code.store'), ['code' => wrongCode($mail)])
        ->assertTooManyRequests();
});

test('an email sent along with the code does not dodge the throttle', function () {
    User::factory()->create(['email' => 'marta@example.com']);
    $mail = requestMagicLink();

    foreach (range(1, 4) as $attempt) {
        $this->post(route('magic-link.code.store'), ['code' => wrongCode($mail), 'email' => "other{$attempt}@example.com"]);
    }

    $this->post(route('magic-link.code.store'), ['code' => wrongCode($mail), 'email' => 'yet-another@example.com'])
        ->assertTooManyRequests();
});

test('an email saved with capital letters can still ask for a link', function () {
    $user = User::factory()->create(['email' => 'Marta.Puig@Example.com']);

    expect($user->fresh()->email)->toBe('marta.puig@example.com');

    $mail = requestMagicLink('Marta.Puig@Example.com');

    expect($mail?->hasTo('marta.puig@example.com'))->toBeTrue();
});

test('an email that differs only in capital letters is taken', function () {
    User::factory()->create(['email' => 'marta@example.com']);
    $other = User::factory()->create();

    $this->actingAs($other)
        ->patch(route('profile.update'), ['name' => $other->name, 'email' => 'MARTA@example.com'])
        ->assertSessionHasErrors('email');
});

test('requests from many emails on one address are throttled', function () {
    foreach (range(1, 20) as $attempt) {
        $this->post(route('magic-link.store'), ['email' => "person{$attempt}@example.com"]);
    }

    $this->post(route('magic-link.store'), ['email' => 'one-more@example.com'])
        ->assertTooManyRequests();
});
