<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;

test('users can sign in with their password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('home', absolute: false));
});

test('users cannot sign in with a wrong password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a wrong password does not reveal whether the email exists', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

test('users can sign out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('sign-in attempts are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});

test('every password attempt is timeboxed, so response time does not reveal whether the email exists', function () {
    $timebox = new class extends Timebox
    {
        /** @var list<int> */
        public array $durations = [];

        public function call(callable $callback, int $microseconds)
        {
            $this->durations[] = $microseconds;

            return parent::call($callback, 0);
        }
    };
    $this->app->instance(Timebox::class, $timebox);

    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong-password']);

    expect($timebox->durations)->toBe([200_000]);
});
