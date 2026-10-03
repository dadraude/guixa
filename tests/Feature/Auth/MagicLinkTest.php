<?php

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

/**
 * Create the owner through the command and return the link from their email.
 */
function ownerMagicLink(): string
{
    test()->artisan('guixa:create-owner', ['email' => 'anna@example.com', 'name' => 'Anna Puig'])
        ->assertSuccessful();

    $url = null;

    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    return $url;
}

test('opening the link asks for one click, so mail scanners cannot use it up', function () {
    $url = ownerMagicLink();

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/MagicLinkConfirm'));
    $this->get($url)->assertOk();

    $this->assertGuest();

    $this->post($url)->assertRedirect(route('home'));

    $this->assertAuthenticated();
});

test('opening a valid magic link signs the person in', function () {
    $url = ownerMagicLink();

    $this->post($url)->assertRedirect(route('home'));

    $this->assertAuthenticatedAs(User::where('email', 'anna@example.com')->sole());
});

test('a magic link works only once', function () {
    $url = ownerMagicLink();

    $this->post($url);
    auth()->logout();

    $this->post($url)
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('This link has expired or has already been used.'));

    $this->assertGuest();
});

test('a magic link expires after 15 minutes', function () {
    $url = ownerMagicLink();

    $this->travel(15)->minutes();
    $this->travel(1)->seconds();

    $this->post($url)->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a magic link still works just before it expires', function () {
    $url = ownerMagicLink();

    $this->travel(14)->minutes();

    $this->post($url)->assertRedirect(route('home'));

    $this->assertAuthenticated();
});

test('an unknown magic link does not sign anyone in', function () {
    ownerMagicLink();

    $this->post(route('magic-link.consume', ['token' => str_repeat('a', 64)]))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
