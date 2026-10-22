<?php

use App\Models\User;
use Guixa\Membership\Infrastructure\Mail\InvitationMail;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FrozenClock;

function inviteAnna(User $manager): array
{
    test()->actingAs($manager)->post(route('manage.households.store'), ['name' => 'Household H']);
    $householdId = DB::table('households')->value('id');
    test()->actingAs($manager)->post(route('manage.households.members.store', $householdId), ['name' => 'Anna', 'email' => 'anna@example.org']);
    auth()->logout();

    $url = null;
    Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use (&$url) {
        $url = $mail->acceptUrl;

        return $mail->hasTo('anna@example.org');
    });

    return [$householdId, $url];
}

beforeEach(function () {
    Mail::fake();
    $this->manager = User::factory()->create();
    DB::table('account_roles')->insert(['user_id' => $this->manager->id, 'role' => Role::Manager->value]);
});

// S25 — Member invitation (R33, R34)

it('refuses login until the invitation is accepted, then lets Anna in with the password she chose (S25)', function () {
    [$householdId, $url] = inviteAnna($this->manager);

    $this->post(route('login.store'), ['email' => 'anna@example.org', 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();

    $this->post($url, ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password'])
        ->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => 'anna@example.org', 'password' => 'a-new-password']);
    $this->assertAuthenticated();
    expect(DB::table('users')->where('email', 'anna@example.org')->value('household_id'))->toBe($householdId);
});

it('refuses login to an invited account even with a password reset', function () {
    inviteAnna($this->manager);
    DB::table('users')->where('email', 'anna@example.org')->update(['password' => Hash::make('guessed')]);

    $this->post(route('login.store'), ['email' => 'anna@example.org', 'password' => 'guessed']);

    $this->assertGuest();
});

it('shows the set-password page for the link', function () {
    [, $url] = inviteAnna($this->manager);

    $this->get($url)->assertOk();
});

it('rejects a link older than 7 days with a form error (S25)', function () {
    [, $url] = inviteAnna($this->manager);

    $this->app->instance(Clock::class, new FrozenClock(now()->addDays(7)->addMinute()->toIso8601String()));

    $this->from($url)->post($url, ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password'])
        ->assertSessionHasErrors('domain');
    $this->assertGuest();
});

it('rejects a link replaced by a resent one (S25)', function () {
    [, $oldUrl] = inviteAnna($this->manager);
    $accountId = DB::table('users')->where('email', 'anna@example.org')->value('id');

    $this->actingAs($this->manager)->post(route('manage.accounts.invitation.resend', $accountId))->assertRedirect();
    auth()->logout();

    $this->from($oldUrl)->post($oldUrl, ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password'])
        ->assertSessionHasErrors('domain');

    $newUrl = null;
    Mail::assertSent(InvitationMail::class, 2);
    Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use (&$newUrl) {
        $newUrl = $mail->acceptUrl;

        return true;
    });
    $this->post($newUrl, ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password'])
        ->assertRedirect(route('login'));
});

it('changes the invitation email and sends the new link there (R34)', function () {
    inviteAnna($this->manager);
    $accountId = DB::table('users')->where('email', 'anna@example.org')->value('id');

    $this->actingAs($this->manager)
        ->put(route('manage.accounts.invitation.email', $accountId), ['email' => 'anna.new@example.org'])
        ->assertRedirect();

    Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail) => $mail->hasTo('anna.new@example.org'));
    expect(DB::table('users')->where('id', $accountId)->value('email'))->toBe('anna.new@example.org');
});

it('requires a confirmed password to accept the invitation', function () {
    [, $url] = inviteAnna($this->manager);

    $this->from($url)->post($url, ['password' => 'a-new-password', 'password_confirmation' => 'different'])
        ->assertSessionHasErrors('password');
});

it('still lets accounts without an invitation log in as before', function () {
    $user = User::factory()->manager()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticated();
});

it('logs in an account whose stored email has mixed case', function () {
    $user = User::factory()->manager()->create(['email' => 'Anna@Example.org']);

    $this->post(route('login.store'), ['email' => 'anna@example.org', 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});
