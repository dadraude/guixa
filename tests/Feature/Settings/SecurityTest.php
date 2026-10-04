<?php

use App\Auth\MagicLinkBroker;
use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

test('the security page is displayed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/Security'));
});

test('the password can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('the current password is required to update the password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});

test('the current password is not asked after signing in with a magic link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['signed_in_with_magic_link' => true])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('requiresCurrentPassword', false));

    $this->actingAs($user)
        ->withSession(['signed_in_with_magic_link' => true])
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('the current password is asked after a password sign-in', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('requiresCurrentPassword', true));
});

test('signing in with a magic link marks the session', function () {
    $user = User::factory()->create();
    $token = app(MagicLinkBroker::class);
    Mail::fake();
    $token->send($user);
    $url = null;
    Mail::assertQueued(MagicLinkMail::class, function ($mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    $this->post($url);

    expect(session('signed_in_with_magic_link'))->toBeTrue();
});
