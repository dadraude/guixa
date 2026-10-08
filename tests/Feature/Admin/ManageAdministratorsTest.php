<?php

declare(strict_types=1);

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

function promotableMember(string $name = 'Berta'): User
{
    return User::factory()->member(Household::factory()->create())->create(['name' => $name]);
}

test('the owner lists the administrators, each with a member profile', function () {
    $owner = User::factory()->owner()->create(['name' => 'Anna']);
    User::factory()->administrator()->create(['name' => 'Berta']);

    $this->actingAs($owner)
        ->get(route('admin.administrators.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Administrators')
            ->has('administrators', 2)
            ->where('administrators.0.name', 'Anna')
            ->where('administrators.0.is_owner', true)
            ->where('administrators.1.name', 'Berta')
            ->where('administrators.1.is_owner', false)
            ->missing('administrators.1.is_active'));
});

test('the picker lists only active members who are not administrators', function () {
    $owner = User::factory()->owner()->create(['name' => 'Anna']);
    promotableMember('Carles');
    promotableMember('Berta');
    User::factory()->administrator()->create(['name' => 'Already']);
    promotableMember('Gone')->member->update(['is_active' => false]);

    $this->actingAs($owner)
        ->get(route('admin.administrators.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('candidates', 2)
            ->where('candidates.0.name', 'Berta')
            ->where('candidates.1.name', 'Carles'));
});

test('only the owner is shown the administrators menu entry', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('admin.home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isOwner', true));

    $this->actingAs(User::factory()->administrator()->create())
        ->get(route('admin.home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isOwner', false));
});

test('the owner adds the administrator profile to an active member and no email is sent', function () {
    $owner = User::factory()->owner()->create();
    $berta = promotableMember();

    $this->actingAs($owner)
        ->post(route('admin.administrators.store'), ['member_id' => $berta->member->id])
        ->assertRedirect(route('admin.administrators.index'));

    expect($berta->fresh()->administrator)->not->toBeNull()
        ->and($berta->fresh()->administrator->is_owner)->toBeFalse()
        ->and($berta->fresh()->member->is_active)->toBeTrue()
        ->and(User::count())->toBe(2);

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

test('an inactive member cannot be made an administrator', function () {
    $owner = User::factory()->owner()->create();
    $berta = promotableMember();
    $berta->member->update(['is_active' => false]);

    $this->actingAs($owner)
        ->post(route('admin.administrators.store'), ['member_id' => $berta->member->id])
        ->assertSessionHasErrors('member_id');

    expect($berta->fresh()->administrator)->toBeNull();
});

test('adding the profile to an existing administrator is refused', function () {
    $owner = User::factory()->owner()->create();
    $berta = User::factory()->administrator()->create();
    $administratorId = $berta->administrator->id;

    $this->actingAs($owner)
        ->post(route('admin.administrators.store'), ['member_id' => $berta->member->id])
        ->assertSessionHasErrors('member_id');

    $this->actingAs($owner)
        ->post(route('admin.administrators.store'), ['member_id' => $owner->member->id])
        ->assertSessionHasErrors('member_id');

    expect($berta->fresh()->administrator->id)->toBe($administratorId)
        ->and(DB::table('administrators')->count())->toBe(2);
});

test('adding a member that does not exist is a validation error', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('admin.administrators.store'), ['member_id' => (string) Str::uuid()])
        ->assertSessionHasErrors('member_id');

    $this->actingAs($owner)
        ->post(route('admin.administrators.store'), [])
        ->assertSessionHasErrors('member_id');
});

test('an administrator creating one by email is no longer possible', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('admin.administrators.store'), ['name' => 'Berta', 'email' => 'berta@example.com'])
        ->assertSessionHasErrors('member_id');

    expect(User::where('email', 'berta@example.com')->exists())->toBeFalse();
});

test('the owner removes the administrator profile and the person stays an active member', function () {
    $owner = User::factory()->owner()->create();
    $berta = User::factory()->administrator()->member(Household::factory()->create())->create();

    $this->actingAs($owner)
        ->delete(route('admin.administrators.destroy', $berta->administrator))
        ->assertRedirect(route('admin.administrators.index'));

    expect($berta->fresh()->administrator)->toBeNull()
        ->and($berta->fresh()->member->is_active)->toBeTrue()
        ->and(User::count())->toBe(2);
});

test('the owner adds the profile again to a returning helper', function () {
    $owner = User::factory()->owner()->create();
    $berta = User::factory()->administrator()->create();
    $berta->administrator->delete();

    $this->actingAs($owner)
        ->post(route('admin.administrators.store'), ['member_id' => $berta->member->id])
        ->assertRedirect(route('admin.administrators.index'));

    expect($berta->fresh()->administrator)->not->toBeNull();
});

test('the owner can remove the profile of an administrator who is inactive as a member', function () {
    $owner = User::factory()->owner()->create();
    $berta = User::factory()->administrator()->create();
    $berta->member->update(['is_active' => false]);

    $this->actingAs($owner)
        ->delete(route('admin.administrators.destroy', $berta->administrator))
        ->assertRedirect(route('admin.administrators.index'));

    expect($berta->fresh()->administrator)->toBeNull();
});

test('the owner never loses the administrator profile from the app', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->delete(route('admin.administrators.destroy', $owner->administrator))
        ->assertForbidden();

    expect($owner->fresh()->administrator)->not->toBeNull();
});

test('administrators and members can neither add nor remove the profile', function () {
    $administrator = User::factory()->administrator()->create();
    $other = User::factory()->administrator()->create();
    $candidate = promotableMember();
    $member = promotableMember('Plain');

    foreach ([$administrator, $member] as $actor) {
        $this->actingAs($actor)->get(route('admin.administrators.index'))->assertForbidden();
        $this->actingAs($actor)
            ->post(route('admin.administrators.store'), ['member_id' => $candidate->member->id])
            ->assertForbidden();
        $this->actingAs($actor)
            ->delete(route('admin.administrators.destroy', $other->administrator))
            ->assertForbidden();
    }

    expect($candidate->fresh()->administrator)->toBeNull()
        ->and($other->fresh()->administrator)->not->toBeNull();
});

test('the owner acting as a member cannot manage administrators', function () {
    $owner = User::factory()->owner()->member(Household::factory()->create())->create();
    $other = User::factory()->administrator()->create();

    $this->actingAs($owner)->withSession(['active_profile' => 'member']);

    $this->get(route('admin.administrators.index'))->assertForbidden();
    $this->post(route('admin.administrators.store'), ['member_id' => $other->member->id])->assertForbidden();
    $this->delete(route('admin.administrators.destroy', $other->administrator))->assertForbidden();
});

test('removing the profile ends the administrator session and acting as member keeps working', function () {
    $owner = User::factory()->owner()->create();
    $berta = User::factory()->administrator()->member(Household::factory()->create())->create(['email' => 'berta@example.com']);

    $this->post(route('login.store'), ['email' => 'berta@example.com', 'password' => 'password']);
    $this->post(route('active-profile.update'), ['profile' => 'administrator']);
    $this->get(route('admin.home'))->assertOk();
    $bertaSession = session()->all();

    $this->flushSession();
    auth()->forgetGuards();
    $this->actingAs($owner)->delete(route('admin.administrators.destroy', $berta->administrator))
        ->assertRedirect(route('admin.administrators.index'));

    $this->flushSession();
    auth()->forgetGuards();
    $this->withSession($bertaSession)->get(route('admin.home'))->assertForbidden();
    $this->get(route('home'))->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk();
    $this->assertAuthenticatedAs($berta);
});

test('re-adding the profile mid-session does not resume the old administrator choice', function () {
    $owner = User::factory()->owner()->create();
    $berta = User::factory()->administrator()->member(Household::factory()->create())->create(['email' => 'berta@example.com']);

    $this->post(route('login.store'), ['email' => 'berta@example.com', 'password' => 'password']);
    $this->post(route('active-profile.update'), ['profile' => 'administrator']);
    $bertaSession = session()->all();

    $this->flushSession();
    auth()->forgetGuards();
    $this->actingAs($owner)->delete(route('admin.administrators.destroy', $berta->administrator));

    $this->flushSession();
    auth()->forgetGuards();
    $this->withSession($bertaSession)->get(route('dashboard'))->assertOk();
    $bertaSession = session()->all();

    $this->flushSession();
    auth()->forgetGuards();
    $this->actingAs($owner)->post(route('admin.administrators.store'), ['member_id' => $berta->member->id]);

    $this->flushSession();
    auth()->forgetGuards();
    $this->withSession($bertaSession)->get(route('dashboard'))->assertRedirect(route('active-profile.edit'));
});

test('a member whose profile is removed while signed in as administrator without a household is sent to the waiting screen', function () {
    $owner = User::factory()->owner()->create();
    $berta = User::factory()->administrator()->create(['email' => 'berta@example.com']);

    $this->post(route('login.store'), ['email' => 'berta@example.com', 'password' => 'password']);
    $this->get(route('admin.home'))->assertOk();
    $bertaSession = session()->all();

    $this->flushSession();
    auth()->forgetGuards();
    $this->actingAs($owner)->delete(route('admin.administrators.destroy', $berta->administrator));

    $this->flushSession();
    auth()->forgetGuards();
    $this->withSession($bertaSession)->get(route('admin.home'))->assertRedirect(route('waiting'));
    $this->assertAuthenticatedAs($berta);
});

test('a new administrator is not asked to choose a profile when the member has no household', function () {
    $owner = User::factory()->owner()->create();
    $berta = User::factory()->memberWithoutHousehold()->create(['email' => 'berta@example.com']);

    $this->actingAs($owner)->post(route('admin.administrators.store'), ['member_id' => $berta->member->id]);

    $this->flushSession();
    auth()->forgetGuards();
    $this->post(route('login.store'), ['email' => 'berta@example.com', 'password' => 'password']);
    $this->get(route('admin.home'))->assertOk();
});

test('an inactive owner-less user without an administrator profile is not signed out', function () {
    $user = User::factory()->withoutPassword()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.setup'));
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
    foreach (['Administrators', 'Add administrator', 'Remove', 'Owner', 'Choose a member', 'Only active members can become administrators.', 'This member is already an administrator.'] as $key) {
        expect(__($key))->not->toBe($key);
    }
});
