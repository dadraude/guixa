<?php

use App\Mail\EmailChangeMail;
use App\Mail\MagicLinkMail;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An owner who is also a member of a household.
 */
function ownerAndMember(): User
{
    return User::factory()->owner()->member(Household::factory()->create(['name' => 'Cal Pere']))->create();
}

/**
 * Ask for a magic link from the sign-in screen and return the queued email.
 */
function magicLinkFor(User $user): MagicLinkMail
{
    Mail::fake();
    test()->post(route('magic-link.store'), ['email' => $user->email]);

    return Mail::queued(MagicLinkMail::class)->last();
}

test('signing in with a password asks a person with both profiles to choose', function () {
    $user = ownerAndMember();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->get(route('home'))->assertRedirect(route('active-profile.edit'));
    $this->assertAuthenticatedAs($user);
});

test('signing in with a magic link asks a person with both profiles to choose', function () {
    $mail = magicLinkFor(ownerAndMember());

    $this->post($mail->url)->assertRedirect(route('home'));

    $this->get(route('home'))->assertRedirect(route('active-profile.edit'));
});

test('signing in with the code asks a person with both profiles to choose', function () {
    $mail = magicLinkFor(ownerAndMember());

    $this->post(route('magic-link.code.store'), ['code' => $mail->code])->assertRedirect(route('home'));

    $this->get(route('home'))->assertRedirect(route('active-profile.edit'));
});

test('a protected page asked for before signing in is where the choice leads, whatever the sign-in method', function (string $method) {
    $user = ownerAndMember();
    $this->get(route('households.index'))->assertRedirect(route('login'));

    match ($method) {
        'password' => $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']),
        'link' => $this->post(magicLinkFor($user)->url),
        'code' => $this->post(route('magic-link.code.store'), ['code' => magicLinkFor($user)->code]),
    };

    $this->get(route('households.index'))->assertRedirect(route('active-profile.edit'));

    $this->post(route('active-profile.update'), ['profile' => 'administrator'])
        ->assertRedirect(route('households.index'));
})->with(['password', 'link', 'code']);

test('choosing a profile with no page asked for leads home', function () {
    $this->actingAs(ownerAndMember())
        ->post(route('active-profile.update'), ['profile' => 'member'])
        ->assertRedirect(route('home'));
});

test('until they choose, a person with both profiles cannot reach any page', function () {
    $this->actingAs(ownerAndMember());

    $this->get(route('admin.home'))->assertRedirect(route('active-profile.edit'));
    $this->get(route('dashboard'))->assertRedirect(route('active-profile.edit'));
    $this->get(route('households.index'))->assertRedirect(route('active-profile.edit'));
});

test('the choice screen offers both profiles in Catalan', function () {
    $this->actingAs(ownerAndMember())
        ->get(route('active-profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/ChooseProfile')
            ->where('profiles', ['member', 'administrator'])
            ->where('auth.actingAs', null));

    expect(__('Act as member'))->toBe('Actua com a membre')
        ->and(__('Act as administrator'))->toBe('Actua com a administrador/a')
        ->and(__('Acting as member'))->toBe('Actues com a membre')
        ->and(__('Choose how you want to act'))->toBe('Tria com vols actuar')
        ->and(__('Switch to member'))->toBe('Canvia a membre')
        ->and(__('Switch to administrator'))->toBe('Canvia a administrador/a');
});

test('choosing member lands on the member home and refuses administrator pages', function () {
    $this->actingAs(ownerAndMember());

    $this->post(route('active-profile.update'), ['profile' => 'member'])->assertRedirect(route('home'));

    $this->get(route('home'))->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk();
    $this->get(route('admin.home'))->assertForbidden();
    $this->get(route('households.index'))->assertForbidden();
    $this->get(route('admin.administrators.index'))->assertForbidden();
});

test('acting as member never passes the administrator rule, not even as owner', function () {
    $user = ownerAndMember();
    $this->actingAs($user)->post(route('active-profile.update'), ['profile' => 'member']);

    expect(Gate::forUser($user)->allows('administer'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('manage-membership'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('manage-administrators'))->toBeFalse();
});

test('choosing administrator lands on the administrator home and allows administrator pages', function () {
    $user = ownerAndMember();
    $this->actingAs($user)->post(route('active-profile.update'), ['profile' => 'administrator'])
        ->assertRedirect(route('home'));

    $this->get(route('home'))->assertRedirect(route('admin.home'));
    $this->get(route('admin.home'))->assertOk();
    $this->get(route('households.index'))->assertOk();
    $this->get(route('admin.administrators.index'))->assertOk();
});

test('switching profile needs no new sign-in', function () {
    $user = ownerAndMember();
    $this->actingAs($user)->post(route('active-profile.update'), ['profile' => 'member']);
    $this->get(route('admin.home'))->assertForbidden();

    $this->post(route('active-profile.update'), ['profile' => 'administrator']);

    $this->get(route('admin.home'))->assertOk();
    $this->assertAuthenticatedAs($user);

    $this->post(route('active-profile.update'), ['profile' => 'member']);

    $this->get(route('admin.home'))->assertForbidden();
});

test('the layout always shows which profile is active and that it can be switched', function () {
    $this->actingAs(ownerAndMember())->post(route('active-profile.update'), ['profile' => 'member']);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.actingAs', 'member')
        ->where('auth.profiles', ['member', 'administrator']));

    $this->post(route('active-profile.update'), ['profile' => 'administrator']);

    $this->get(route('admin.home'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.actingAs', 'administrator')
        ->where('auth.profiles', ['member', 'administrator']));
});

test('an administrator acting as one does not see the household of their member profile', function () {
    $this->actingAs(ownerAndMember())->post(route('active-profile.update'), ['profile' => 'administrator']);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('household', null));

    $this->post(route('active-profile.update'), ['profile' => 'member']);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('household.name', 'Cal Pere'));
});

test('a profile the person does not have cannot be chosen', function () {
    $administrator = User::factory()->administrator()->create();
    $member = User::factory()->member(Household::factory()->create())->create();

    $this->actingAs($administrator)->post(route('active-profile.update'), ['profile' => 'member'])
        ->assertSessionHasErrors('profile');
    $this->actingAs($member)->post(route('active-profile.update'), ['profile' => 'administrator'])
        ->assertSessionHasErrors('profile');
    $this->actingAs($member)->post(route('active-profile.update'), ['profile' => 'owner'])
        ->assertSessionHasErrors('profile');

    $this->get(route('admin.home'))->assertForbidden();
});

test('an administrator without a member profile goes straight to the administrator home', function () {
    $this->actingAs(User::factory()->administrator()->create());

    $this->get(route('home'))->assertRedirect(route('admin.home'));
    $this->get(route('admin.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.actingAs', 'administrator')
            ->where('auth.profiles', ['administrator']));
    $this->get(route('active-profile.edit'))->assertRedirect(route('home'));
});

test('a member without an administrator profile goes straight to the member home', function () {
    $this->actingAs(User::factory()->member(Household::factory()->create())->create());

    $this->get(route('home'))->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.actingAs', 'member')
            ->where('auth.profiles', ['member']));
    $this->get(route('active-profile.edit'))->assertRedirect(route('home'));
});

test('an inactive administrator who is also a member signs in as member only', function () {
    $user = User::factory()->administrator(active: false)->member(Household::factory()->create())->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
    $this->get(route('home'))->assertRedirect(route('dashboard'));
    $this->get(route('admin.home'))->assertForbidden();
    $this->post(route('active-profile.update'), ['profile' => 'administrator'])->assertSessionHasErrors('profile');
});

test('deactivating the administrator profile ends the administrator session of someone acting as one', function () {
    $user = ownerAndMember();
    $this->actingAs($user)->post(route('active-profile.update'), ['profile' => 'administrator']);
    $this->get(route('admin.home'))->assertOk();

    $user->administrator->update(['is_active' => false]);

    $this->get(route('admin.home'))->assertForbidden();
    $this->get(route('home'))->assertRedirect(route('dashboard'));
});

test('a person with both profiles who has no password is first asked to set one', function () {
    $user = User::factory()->withoutPassword()->owner()->member(Household::factory()->create())->create();

    $this->actingAs($user)->get(route('home'))->assertRedirect(route('password.setup'));
    $this->get(route('password.setup'))->assertOk();
});

test('the choice is forgotten when signing out', function () {
    $user = ownerAndMember();
    $this->actingAs($user)->post(route('active-profile.update'), ['profile' => 'administrator']);

    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->get(route('home'))->assertRedirect(route('active-profile.edit'));
});

test('changing the email changes it for both profiles', function () {
    $user = ownerAndMember();

    Mail::fake();

    $this->actingAs($user)->post(route('active-profile.update'), ['profile' => 'member']);
    $this->patch(route('profile.update'), ['name' => $user->name, 'email' => 'nou@example.com']);

    $confirmationUrl = null;
    Mail::assertQueued(EmailChangeMail::class, function (EmailChangeMail $mail) use (&$confirmationUrl) {
        $confirmationUrl = $mail->url;

        return true;
    });
    $this->post($confirmationUrl);

    expect($user->administrator->fresh()->user->email)->toBe('nou@example.com')
        ->and($user->member->fresh()->user->email)->toBe('nou@example.com');
});

test('a request loads the profiles of the signed-in user once, not again for the sign-in check', function () {
    $user = User::factory()->member(Household::factory()->create())->create();
    $this->actingAs($user);

    $queries = collect();
    DB::listen(fn ($query) => $queries->push($query->sql));

    $this->get(route('dashboard'))->assertOk();

    $profileQueries = fn (string $table) => $queries
        ->filter(fn (string $sql) => str_contains($sql, "from \"{$table}\" where \"{$table}\".\"user_id\""))
        ->count();

    expect($profileQueries('administrators'))->toBe(1)
        ->and($profileQueries('members'))->toBe(1);
});
