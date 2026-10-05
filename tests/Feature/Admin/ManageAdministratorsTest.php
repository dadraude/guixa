<?php

declare(strict_types=1);

use App\Actions\CreateAdministrator;
use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

test('the owner lists the administrators with their state', function () {
    $owner = User::factory()->owner()->create(['name' => 'Anna']);
    User::factory()->administrator()->create(['name' => 'Berta']);
    User::factory()->administrator(active: false)->create(['name' => 'Carles']);

    $this->actingAs($owner)
        ->get(route('admin.administrators.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Administrators')
            ->has('administrators', 3)
            ->where('administrators.0.name', 'Anna')
            ->where('administrators.0.is_owner', true)
            ->where('administrators.1.name', 'Berta')
            ->where('administrators.1.is_active', true)
            ->where('administrators.2.name', 'Carles')
            ->where('administrators.2.is_active', false));
});

test('only the owner is shown the administrators menu entry', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('admin.home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isOwner', true));

    $this->actingAs(User::factory()->administrator()->create())
        ->get(route('admin.home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isOwner', false));
});

test('the owner creates an administrator and a magic link is queued for them', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('admin.administrators.store'), ['name' => 'Berta Soler', 'email' => 'Berta@Example.com'])
        ->assertRedirect(route('admin.administrators.index'));

    $user = User::where('email', 'berta@example.com')->sole();

    expect($user->name)->toBe('Berta Soler')
        ->and($user->password)->toBeNull()
        ->and($user->administrator->is_active)->toBeTrue()
        ->and($user->administrator->is_owner)->toBeFalse();

    Mail::assertQueued(MagicLinkMail::class, fn (MagicLinkMail $mail) => $mail->hasTo('berta@example.com'));
});

test('a new administrator is asked to set a password on first access', function () {
    $owner = User::factory()->owner()->create();
    $this->actingAs($owner)
        ->post(route('admin.administrators.store'), ['name' => 'Berta', 'email' => 'berta@example.com']);
    $mail = Mail::queued(MagicLinkMail::class)->last();
    $this->flushSession();
    auth()->forgetGuards();

    $this->post(preg_replace('#^https?://[^/]+#', '', $mail->url))->assertRedirect(route('home'));
    $this->get(route('admin.home'))->assertRedirect(route('password.setup'));
});

test('creating an administrator validates the name and a unique email, ignoring case', function () {
    $owner = User::factory()->owner()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($owner)
        ->post(route('admin.administrators.store'), ['name' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['name', 'email']);

    $this->actingAs($owner)
        ->post(route('admin.administrators.store'), ['name' => 'Dup', 'email' => 'TAKEN@example.com'])
        ->assertSessionHasErrors('email');

    expect(User::count())->toBe(2);
    Mail::assertNothingQueued();
});

test('an administrator who is not the owner is refused', function () {
    $administrator = User::factory()->administrator()->create();
    $other = User::factory()->administrator()->create();
    $inactive = User::factory()->administrator(active: false)->create();

    $this->actingAs($administrator)->get(route('admin.administrators.index'))->assertForbidden();
    $this->actingAs($administrator)
        ->post(route('admin.administrators.store'), ['name' => 'X', 'email' => 'x@example.com'])
        ->assertForbidden();
    $this->actingAs($administrator)
        ->post(route('admin.administrators.deactivate', $other->administrator))
        ->assertForbidden();
    $this->actingAs($administrator)
        ->post(route('admin.administrators.reactivate', $inactive->administrator))
        ->assertForbidden();

    expect($other->administrator->fresh()->is_active)->toBeTrue()
        ->and($inactive->administrator->fresh()->is_active)->toBeFalse()
        ->and(User::where('email', 'x@example.com')->exists())->toBeFalse();
    Mail::assertNothingQueued();
});

test('a member who is not an administrator is refused', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.administrators.index'))
        ->assertForbidden();
});

test('the owner deactivates and reactivates an administrator keeping the same account', function () {
    $owner = User::factory()->owner()->create();
    $user = User::factory()->administrator()->create();
    $administratorId = $user->administrator->id;

    $this->actingAs($owner)
        ->post(route('admin.administrators.deactivate', $user->administrator))
        ->assertRedirect(route('admin.administrators.index'));
    expect($user->administrator->fresh()->is_active)->toBeFalse();

    $this->actingAs($owner)
        ->post(route('admin.administrators.reactivate', $user->administrator))
        ->assertRedirect(route('admin.administrators.index'));

    expect($user->administrator->fresh()->is_active)->toBeTrue()
        ->and($user->administrator->fresh()->id)->toBe($administratorId)
        ->and(User::count())->toBe(2);
});

test('the owner cannot be deactivated', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('admin.administrators.deactivate', $owner->administrator))
        ->assertForbidden();

    expect($owner->administrator->fresh()->is_active)->toBeTrue();
});

test('an administrator who is deactivated while signed in is signed out on their next request', function () {
    $user = User::factory()->administrator()->create(['email' => 'berta@example.com']);

    $this->post(route('login.store'), ['email' => 'berta@example.com', 'password' => 'password'])
        ->assertRedirect(route('home'));
    $this->get(route('admin.home'))->assertOk();

    $user->administrator->update(['is_active' => false]);

    $this->get(route('admin.home'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the owner ends a signed-in administrator\'s session through the route and can restore their access', function () {
    $owner = User::factory()->owner()->create();
    $berta = User::factory()->administrator()->create(['email' => 'berta@example.com']);

    $this->post(route('login.store'), ['email' => 'berta@example.com', 'password' => 'password']);
    $this->get(route('admin.home'))->assertOk();
    $bertaSession = session()->all();

    $this->flushSession();
    auth()->forgetGuards();
    $this->actingAs($owner)->post(route('admin.administrators.deactivate', $berta->administrator))
        ->assertRedirect(route('admin.administrators.index'));

    $this->flushSession();
    auth()->forgetGuards();
    $this->withSession($bertaSession)->get(route('admin.home'))->assertRedirect(route('login'));
    $this->assertGuest();

    $this->flushSession();
    auth()->forgetGuards();
    $this->actingAs($owner)->post(route('admin.administrators.reactivate', $berta->administrator));

    $this->flushSession();
    auth()->forgetGuards();
    $this->post(route('magic-link.store'), ['email' => 'berta@example.com']);
    $mail = Mail::queued(MagicLinkMail::class)->last();
    $this->post(route('magic-link.code.store'), ['code' => $mail->code])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($berta);
    expect(User::where('email', 'berta@example.com')->sole()->id)->toBe($berta->id);
});

test('deactivating an inactive administrator or reactivating an active one changes nothing', function () {
    $owner = User::factory()->owner()->create();
    $active = User::factory()->administrator()->create();
    $inactive = User::factory()->administrator(active: false)->create();

    $this->actingAs($owner)->post(route('admin.administrators.reactivate', $active->administrator))
        ->assertRedirect(route('admin.administrators.index'));
    $this->actingAs($owner)->post(route('admin.administrators.deactivate', $inactive->administrator))
        ->assertRedirect(route('admin.administrators.index'));

    expect($active->administrator->fresh()->is_active)->toBeTrue()
        ->and($inactive->administrator->fresh()->is_active)->toBeFalse();
});

test('the owner cannot be targeted by reactivate either', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('admin.administrators.reactivate', $owner->administrator))
        ->assertForbidden();
});

test('an inactive owner-less user without an administrator profile is not signed out', function () {
    $user = User::factory()->withoutPassword()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.setup'));
    $this->assertAuthenticatedAs($user);
});

test('an inactive administrator cannot sign in with a password', function () {
    User::factory()->administrator(active: false)->create(['email' => 'berta@example.com']);

    $this->post(route('login.store'), ['email' => 'berta@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an inactive administrator gets no magic link and the response does not change', function () {
    User::factory()->administrator(active: false)->create(['email' => 'berta@example.com']);

    $this->post(route('magic-link.store'), ['email' => 'berta@example.com'])
        ->assertRedirect(route('magic-link.code'))
        ->assertSessionHas('status', __('If this address belongs to someone in the cooperative, we have emailed them a link and a code to log in.'));

    Mail::assertNothingQueued();
});

test('a magic link or code issued before deactivation no longer signs the administrator in', function () {
    $user = User::factory()->administrator()->create(['email' => 'berta@example.com']);

    $this->post(route('magic-link.store'), ['email' => 'berta@example.com']);
    $mail = Mail::queued(MagicLinkMail::class)->last();
    $user->administrator->update(['is_active' => false]);

    $this->post(route('magic-link.code.store'), ['code' => $mail->code])->assertSessionHasErrors('code');
    $this->assertGuest();

    $this->post(preg_replace('#^https?://[^/]+#', '', $mail->url))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a reactivated administrator can sign in again', function () {
    $user = User::factory()->administrator(active: false)->create(['email' => 'berta@example.com']);
    $user->administrator->update(['is_active' => true]);

    $this->post(route('login.store'), ['email' => 'berta@example.com', 'password' => 'password'])
        ->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($user);
});

test('emails are unique ignoring case in the database', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    expect(fn () => DB::table('users')->insert([
        'id' => (string) Str::uuid(),
        'name' => 'Dup',
        'email' => 'ANNA@example.com',
    ]))->toThrow(QueryException::class);
});

test('the administrator screen text is in Catalan', function () {
    foreach (['Administrators', 'Add administrator', 'Deactivate', 'Reactivate', 'Active', 'Inactive', 'Owner'] as $key) {
        expect(__($key))->not->toBe($key);
    }
});

test('creating an administrator whose email was taken meanwhile is a validation error, not a crash', function () {
    User::factory()->create(['email' => 'berta@example.com']);

    expect(fn () => app(CreateAdministrator::class)->handle('Berta', 'berta@example.com'))
        ->toThrow(ValidationException::class);
});
