<?php

declare(strict_types=1);

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

test('changing a password keeps this session and ends another session', function (string $screen) {
    Mail::fake();
    $user = User::factory()->owner()->create(['password' => $screen === 'setup' ? null : 'password']);

    $openSession = function () use ($user): array {
        $this->post(route('magic-link.store'), ['email' => $user->email]);
        $mail = Mail::queued(MagicLinkMail::class)->last();
        $this->post($mail->url)->assertRedirect(route('home'));

        return session()->all();
    };
    $otherSession = $openSession();
    $this->flushSession();
    Auth::forgetGuards();
    $openSession();

    postOrPutPassword($screen);
    $this->get(route('home'))->assertRedirect(route('admin.home'));
    $this->assertAuthenticatedAs($user);

    $this->flushSession();
    Auth::forgetGuards();
    $this->withSession($otherSession)->get(route('home'))->assertRedirect(route('login'));
    $this->assertGuest();
})->with(['settings', 'setup']);

function postOrPutPassword(string $screen): void
{
    $data = ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password'];
    if ($screen === 'setup') {
        test()->post(route('password.setup.store'), $data)->assertSessionHasNoErrors();
    } else {
        test()->put(route('user-password.update'), $data)->assertSessionHasNoErrors();
    }
}

test('changing a password rotates remember access and refuses the old cookie', function (string $screen) {
    Mail::fake();
    $user = User::factory()->owner()->create(['password' => $screen === 'setup' ? null : 'password']);
    $this->post(route('magic-link.store'), ['email' => $user->email]);
    $this->post(Mail::queued(MagicLinkMail::class)->last()->url);
    $user->refresh();
    $oldToken = $user->remember_token;
    $cookie = implode('|', [$user->id, $oldToken, Auth::guard()->hashPasswordForCookie($user->password)]);
    $cookieName = Auth::guard()->getRecallerName();

    postOrPutPassword($screen);
    expect($user->fresh()->remember_token)->not->toBe($oldToken);
    $this->get(route('home'))->assertRedirect(route('admin.home'));
    $this->assertAuthenticatedAs($user);

    $this->flushSession();
    Auth::forgetGuards();
    $this->withCookie($cookieName, $cookie)->get(route('home'))->assertRedirect(route('login'));
    $this->assertGuest();
})->with(['settings', 'setup']);
