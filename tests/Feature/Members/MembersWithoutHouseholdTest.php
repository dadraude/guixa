<?php

declare(strict_types=1);

use App\Auth\Role;
use App\Mail\EmailChangeMail;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

function waitingUser(string $name = 'Anna Puig'): User
{
    return User::factory()->memberWithoutHousehold()->create(['name' => $name]);
}

function householdlessInCommission(string $name = 'Anna Puig', Role ...$roles): User
{
    $user = waitingUser($name);
    $commission = Commission::factory()->create();
    foreach ($roles as $role) {
        $commission->assignRole($role);
    }
    $commission->members()->attach($user->member);

    return $user;
}

test('a member can exist without a household and existing members keep theirs', function () {
    $household = Household::factory()->create();
    $with = User::factory()->member($household)->create();

    expect(waitingUser()->member->household_id)->toBeNull()
        ->and($with->member->fresh()->household_id)->toBe($household->id);
});

test('a member with no household and no commission is sent to the waiting screen from any page', function (string $route) {
    $this->actingAs(waitingUser())
        ->get(route($route))
        ->assertRedirect(route('waiting'));
})->with(['home', 'dashboard', 'directory.index', 'commissions.index', 'households.index', 'members.index']);

test('every other request of a waiting member is redirected to the waiting screen', function () {
    $user = waitingUser();
    $other = User::factory()->member(Household::factory()->create())->create()->member;

    $this->actingAs($user)->post(route('households.store'), ['name' => 'Cal Nou'])->assertRedirect(route('waiting'));
    $this->post(route('members.deactivate', $other))->assertRedirect(route('waiting'));

    expect(Household::query()->where('name', 'Cal Nou')->exists())->toBeFalse()
        ->and($other->fresh()->is_active)->toBeTrue();
});

test('the waiting screen tells the member the membership commission will assign a household', function () {
    $this->actingAs(waitingUser())
        ->get(route('waiting'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Waiting'));
});

test('signing in lands a waiting member on the waiting screen', function () {
    $user = waitingUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->get(route('home'))->assertRedirect(route('waiting'));
});

test('a waiting member edits their name, phone and password and requests an email change', function () {
    $user = waitingUser();

    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    $this->get(route('security.edit'))->assertOk();

    $this->patch(route('profile.update'), [
        'name' => 'Anna Nova',
        'phone' => '600 111 222',
        'email' => 'nova@example.com',
    ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->name)->toBe('Anna Nova')
        ->and($user->member->phone)->toBe('600 111 222')
        ->and($user->pending_email)->toBe('nova@example.com');

    $this->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'a-new-password-1',
        'password_confirmation' => 'a-new-password-1',
    ])->assertSessionHasNoErrors();
});

test('a waiting member can sign out', function () {
    $this->actingAs(waitingUser())->post(route('logout'))->assertRedirect();

    $this->assertGuest();
});

test('a member with a household never sees the waiting screen', function () {
    $user = User::factory()->member(Household::factory()->create())->create();

    $this->actingAs($user)->get(route('waiting'))->assertRedirect(route('home'));
    $this->get(route('dashboard'))->assertOk();
});

test('a member with a commission but no household uses the app and has no household page', function () {
    $user = householdlessInCommission();

    $this->actingAs($user)->get(route('waiting'))->assertRedirect(route('home'));
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('household', null)
            ->has('commissions', 1));
    $this->get(route('directory.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('isFull', true));
    $this->get(route('commissions.index'))->assertOk();
});

test('a member with a commission but no household can do what their roles allow', function () {
    $user = householdlessInCommission('Marta Vila', Role::Membership);

    $this->actingAs($user)->get(route('households.index'))->assertOk();
    $this->post(route('households.store'), ['name' => 'Cal Nou'])->assertRedirect(route('households.index'));
    expect(Household::query()->where('name', 'Cal Nou')->exists())->toBeTrue();
});

test('a member with a commission but no household has no roles they were not given', function () {
    $user = householdlessInCommission();

    $this->actingAs($user)->get(route('households.index'))->assertForbidden();
});

test('the directory lists only households and their members, never members without a household', function () {
    $viewer = householdlessInCommission('Marta Vila');
    $household = Household::factory()->create(['name' => 'Cal Pere']);
    User::factory()->member($household)->create(['name' => 'Joan Soler']);
    waitingUser('Pere Sense');

    $this->actingAs($viewer)
        ->get(route('directory.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('households', 1)
            ->has('households.0.members', 1)
            ->where('households.0.members.0.name', 'Joan Soler'));
});

test('the commissions page lists commission members with or without a household', function () {
    $without = householdlessInCommission('Marta Vila');
    $with = User::factory()->member(Household::factory()->create())->create(['name' => 'Joan Soler']);
    Commission::query()->first()->members()->attach($with->member);

    $this->actingAs($without)
        ->get(route('commissions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('commissions.0.members', 2)
            ->where('commissions.0.members.0.name', 'Joan Soler')
            ->where('commissions.0.members.1.name', 'Marta Vila'));
});

test('the households warning counts only members currently in each household', function () {
    $staff = householdlessInCommission('Marta Vila', Role::Membership);
    $household = Household::factory()->create(['name' => 'Cal Pere']);
    $anna = User::factory()->member($household)->create()->member;
    Commission::query()->first()->members()->attach($anna);

    $this->actingAs($staff)
        ->get(route('households.index'))
        ->assertInertia(fn (Assert $page) => $page->where('households.0.without_commission', false));

    $anna->update(['household_id' => null]);

    $this->get(route('households.index'))
        ->assertInertia(fn (Assert $page) => $page->where('households.0.without_commission', true));
});

test('a member without a household can be deactivated and reactivated', function () {
    $staff = householdlessInCommission('Marta Vila', Role::Membership);
    $member = waitingUser('Pere Sense')->member;

    $this->actingAs($staff)->post(route('members.deactivate', $member))->assertRedirect(route('members.index'));
    expect($member->fresh()->is_active)->toBeFalse();

    $this->post(route('members.reactivate', $member))->assertRedirect(route('members.index'));
    expect($member->fresh()->is_active)->toBeTrue();
});

test('removing the household of a member with no commission takes them to the waiting screen on their next request', function () {
    $user = User::factory()->member(Household::factory()->create())->create();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    Member::query()->whereKey($user->member->id)->update(['household_id' => null]);

    $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('waiting'));
});

test('an administrator with no member profile is never sent to the waiting screen', function () {
    $this->actingAs(User::factory()->owner()->create())->get(route('admin.home'))->assertOk();
});

test('a waiting member can open the pages they are allowed', function (string $route) {
    $this->actingAs(waitingUser())->get(route($route))->assertOk();
})->with(['profile.edit', 'security.edit', 'appearance.edit']);

test('the settings shortcut leads a waiting member to their profile', function () {
    $this->actingAs(waitingUser())
        ->get(route('settings'))
        ->assertRedirect('/settings/profile');
});

test('an administrator whose member profile has no household and no commission signs in straight as administrator', function () {
    $user = User::factory()->owner()->memberWithoutHousehold()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->get(route('home'))->assertRedirect(route('admin.home'));
    $this->get(route('admin.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.profiles', ['administrator']));
});

test('an administrator who gains a household mid-session keeps acting as administrator and gets the switcher', function () {
    $user = User::factory()->owner()->memberWithoutHousehold()->create();
    $this->actingAs($user)->get(route('admin.home'))->assertOk();

    $user->member->update(['household_id' => Household::factory()->create()->id]);

    $this->get(route('admin.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.actingAs', 'administrator')
            ->where('auth.profiles', ['member', 'administrator']));
});

test('an administrator who gains a commission mid-session keeps acting as administrator and gets the switcher', function () {
    $user = User::factory()->owner()->memberWithoutHousehold()->create();
    $this->actingAs($user)->get(route('admin.home'))->assertOk();

    Commission::factory()->create()->members()->attach($user->member);

    $this->get(route('admin.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.actingAs', 'administrator')
            ->where('auth.profiles', ['member', 'administrator']));
});

test('someone acting as member who loses household and commissions acts as administrator on the next request', function (bool $hasHousehold, bool $hasCommission) {
    $user = User::factory()->owner()->memberWithoutHousehold()->create();
    $commission = Commission::factory()->create();
    $hasHousehold && $user->member->update(['household_id' => Household::factory()->create()->id]);
    $hasCommission && $commission->members()->attach($user->member);
    $this->actingAs($user)->withSession(['active_profile' => 'member'])->get(route('dashboard'))->assertOk();

    $user->member->update(['household_id' => null]);
    $commission->members()->detach($user->member);

    $this->get(route('admin.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.actingAs', 'administrator'));
})->with([
    'household only' => [true, false],
    'commission only' => [false, true],
    'household and commission' => [true, true],
]);

test('an administrator without a usable member profile cannot choose the member profile', function () {
    $user = User::factory()->owner()->memberWithoutHousehold()->create();

    $this->actingAs($user)
        ->post(route('active-profile.update'), ['profile' => 'member'])
        ->assertSessionHasErrors('profile');
});

test('an administrator with a usable member profile still chooses after signing in', function () {
    $user = User::factory()->owner()->member(Household::factory()->create())->create();

    $this->actingAs($user)->get(route('home'))->assertRedirect(route('active-profile.edit'));
});

test('a waiting member without a password sets one and then lands on the waiting screen', function () {
    $user = User::factory()->memberWithoutHousehold()->withoutPassword()->create();

    $this->actingAs($user)->get(route('home'))->assertRedirect(route('password.setup'));

    $this->post(route('password.setup.store'), [
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertRedirect(route('home'));

    expect(Hash::check('a-new-password', $user->fresh()->password))->toBeTrue();
    $this->get(route('home'))->assertRedirect(route('waiting'));
});

test('a waiting member confirms and cancels an email change', function () {
    $user = User::factory()->memberWithoutHousehold()->create(['email' => 'old@example.com']);
    $url = null;
    $capture = function (EmailChangeMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    };

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.com'])
        ->assertSessionHasNoErrors();
    Mail::assertQueued(EmailChangeMail::class, $capture);

    $this->delete(route('email-change.cancel'))->assertRedirect(route('profile.edit'));
    expect($user->fresh()->pending_email)->toBeNull();

    $this->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.com']);
    Mail::assertQueued(EmailChangeMail::class, $capture);

    $this->post($url)->assertRedirect(route('profile.edit'));
    expect($user->fresh()->email)->toBe('new@example.com');
});

test('the waiting flag is shared only with a member who is waiting', function () {
    $this->actingAs(waitingUser())
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isWaiting', true));

    $this->actingAs(householdlessInCommission())
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isWaiting', false));
});

test('the member list works when a member has no household', function () {
    $staff = householdlessInCommission('Marta Vila', Role::Membership);
    waitingUser('Pere Sense');

    $this->actingAs($staff)->get(route('members.index'))->assertOk();
});
