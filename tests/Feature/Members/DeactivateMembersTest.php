<?php

use App\Auth\Role;
use App\Auth\SecretHasher;
use App\Models\Commission;
use App\Models\Household;
use App\Models\MagicLink;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function membershipStaff(): User
{
    $user = User::factory()->member(Household::factory()->create())->create();
    $user->member->update(['is_active' => true]);
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($user->member->fresh());

    return $user->fresh();
}

function activeMemberIn(Household $household, array $user = []): Member
{
    $member = User::factory()->member($household)->create($user)->member->fresh();
    $member->update(['is_active' => true]);

    return $member;
}

test('deactivating a member removes them from every commission and keeps their data', function () {
    $household = Household::factory()->create();
    $member = activeMemberIn($household);
    $commissions = Commission::factory()->count(2)->create();
    $commissions->each(fn (Commission $commission) => $commission->members()->attach($member));

    $this->actingAs(membershipStaff())
        ->post(route('members.deactivate', $member))
        ->assertRedirect(route('members.index'));

    expect($member->fresh()->is_active)->toBeFalse()
        ->and($member->commissions()->count())->toBe(0)
        ->and($member->fresh()->household_id)->toBe($household->id);
});

test('a deactivated member loses the open session on the next request', function () {
    $member = activeMemberIn(Household::factory()->create());
    $this->actingAs(membershipStaff())->post(route('members.deactivate', $member));

    $this->actingAs($member->user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a deactivated member is refused at sign-in by password, code and link', function () {
    Mail::fake();
    $member = activeMemberIn(Household::factory()->create(), ['email' => 'gone@example.com', 'password' => 'secret-password-1']);
    $this->actingAs(membershipStaff())->post(route('members.deactivate', $member));
    auth()->logout();

    $this->post(route('login.store'), ['email' => 'gone@example.com', 'password' => 'secret-password-1'])
        ->assertSessionHasErrors();
    $this->assertGuest();

    $this->post(route('magic-link.store'), ['email' => 'gone@example.com']);
    expect(MagicLink::query()->count())->toBe(0);

    $member->user->magicLinks()->create([
        'token_hash' => SecretHasher::hash('token-123'),
        'code_hash' => SecretHasher::hash('123456'),
        'expires_at' => now()->addHour(),
    ]);
    $this->post(route('magic-link.consume', 'token-123'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a deactivated member who is an active administrator still signs in, only as administrator', function () {
    $member = activeMemberIn(Household::factory()->create(), ['password' => 'secret-password-1']);
    $member->user->administrator()->create(['is_active' => true]);

    $this->actingAs(membershipStaff())->post(route('members.deactivate', $member));
    auth()->logout();

    $this->post(route('login.store'), ['email' => $member->user->email, 'password' => 'secret-password-1'])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($member->user);

    $this->get(route('home'))->assertRedirect(route('admin.home'));
    $this->get(route('admin.home'))->assertOk();
});

test('reactivating a member keeps their history and works while the household is active', function () {
    $member = activeMemberIn(Household::factory()->create());
    $member->update(['is_active' => false]);

    $this->actingAs(membershipStaff())
        ->post(route('members.reactivate', $member))
        ->assertRedirect(route('members.index'));

    expect($member->fresh()->is_active)->toBeTrue();
});

test('reactivating a member of an inactive household is refused until the household is active', function () {
    $household = Household::factory()->inactive()->create();
    $member = activeMemberIn($household);
    $member->update(['is_active' => false]);
    $staff = membershipStaff();

    $this->actingAs($staff)->post(route('members.reactivate', $member))->assertSessionHasErrors('member');
    expect($member->fresh()->is_active)->toBeFalse();

    $this->actingAs($staff)->post(route('households.reactivate', $household));
    $this->flushSession();
    $this->actingAs($staff)->post(route('members.reactivate', $member))->assertSessionHasNoErrors();
    expect($member->fresh()->is_active)->toBeTrue();
});

test('deactivating a household deactivates all its members and keeps number and name', function () {
    $household = Household::factory()->create(['name' => 'Cal Pere']);
    $number = $household->fresh()->number;
    $first = activeMemberIn($household);
    $second = activeMemberIn($household);
    Commission::factory()->create()->members()->attach([$first->id, $second->id]);
    $other = activeMemberIn(Household::factory()->create());

    $this->actingAs(membershipStaff())
        ->post(route('households.deactivate', $household))
        ->assertRedirect(route('households.index'));

    $household->refresh();
    expect($household->is_active)->toBeFalse()
        ->and($household->number)->toBe($number)
        ->and($household->name)->toBe('Cal Pere')
        ->and($first->fresh()->is_active)->toBeFalse()
        ->and($second->fresh()->is_active)->toBeFalse()
        ->and($first->commissions()->count())->toBe(0)
        ->and($other->fresh()->is_active)->toBeTrue();
});

test('reactivating a household keeps its number and leaves its members inactive', function () {
    $household = Household::factory()->create();
    $number = $household->fresh()->number;
    $member = activeMemberIn($household);
    $staff = membershipStaff();

    $this->actingAs($staff)->post(route('households.deactivate', $household));
    $this->actingAs($staff)->post(route('households.reactivate', $household))
        ->assertRedirect(route('households.index'));

    expect($household->fresh()->is_active)->toBeTrue()
        ->and($household->fresh()->number)->toBe($number)
        ->and($member->fresh()->is_active)->toBeFalse();
});

test('administrators deactivate and reactivate too', function () {
    $household = Household::factory()->create();
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)->post(route('households.deactivate', $household))->assertRedirect();
    expect($household->fresh()->is_active)->toBeFalse();

    $this->actingAs($administrator)->post(route('households.reactivate', $household))->assertRedirect();
    expect($household->fresh()->is_active)->toBeTrue();
});

test('a member without the membership role is refused every action', function () {
    $household = Household::factory()->create();
    $member = activeMemberIn($household);
    $plain = User::factory()->member(Household::factory()->create())->create();

    $this->actingAs($plain)->post(route('members.deactivate', $member))->assertForbidden();
    $this->actingAs($plain)->post(route('members.reactivate', $member))->assertForbidden();
    $this->actingAs($plain)->post(route('households.deactivate', $household))->assertForbidden();
    $this->actingAs($plain)->post(route('households.reactivate', $household))->assertForbidden();

    expect($member->fresh()->is_active)->toBeTrue()
        ->and($household->fresh()->is_active)->toBeTrue();
});
