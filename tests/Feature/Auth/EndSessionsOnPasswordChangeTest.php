<?php

declare(strict_types=1);

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
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

test('a later revocation between password commit and session stamping still ends this session', function (string $screen) {
    Mail::fake();
    $user = User::factory()->owner()->create(['password' => $screen === 'setup' ? null : 'password']);
    $this->post(route('magic-link.store'), ['email' => $user->email]);
    $this->post(Mail::queued(MagicLinkMail::class)->last()->url);
    $initialVersion = $user->fresh()->session_version;
    $revoked = false;

    Event::listen(TransactionCommitted::class, function () use ($user, $initialVersion, &$revoked): void {
        if ($revoked) {
            return;
        }

        $current = $user->fresh();
        if ($current->session_version !== $initialVersion + 1) {
            return;
        }

        $revoked = true;
        $current->forceFill([
            'session_version' => $current->session_version + 1,
            'remember_token' => 'a-later-revocation-token',
        ])->saveQuietly();
    });

    postOrPutPassword($screen);
    expect($revoked)->toBeTrue();
    Auth::forgetGuards();
    $this->get(route('home'))->assertRedirect(route('login'));
    $this->assertGuest();
})->with(['settings', 'setup']);
