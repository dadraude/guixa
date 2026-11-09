<?php

use Guixa\Membership\Application\CanLogIn;
use Guixa\Shared\Domain\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

beforeEach(function () {
    $this->manager = accountWith(Role::Manager);
    $this->householdId = (string) Str::uuid7();
    $this->otherHouseholdId = (string) Str::uuid7();
    foreach ([$this->householdId => 'Household H', $this->otherHouseholdId => 'Household J'] as $id => $name) {
        DB::table('households')->insert(['id' => $id, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
    }
});

// S43 — Moving a member (R35, R35a)

it('moves a member to another household and unlinks the last one, which deactivates its household (S43)', function () {
    $anna = memberOf($this->householdId);

    $this->actingAs($this->manager)
        ->put(route('manage.accounts.household.update', $anna->id), ['household_id' => $this->otherHouseholdId])
        ->assertRedirect();

    expect($anna->fresh()->household_id)->toBe($this->otherHouseholdId)
        ->and(DB::table('households')->where('id', $this->householdId)->value('deactivated_at'))->not->toBeNull();

    $this->actingAs($this->manager)->delete(route('manage.accounts.household.destroy', $anna->id))->assertRedirect();

    expect($anna->fresh()->household_id)->toBeNull()
        ->and($anna->fresh()->deactivated_at)->toBeNull();
});

it('lists the accounts with the households they can be linked to', function () {
    memberOf($this->householdId);

    $this->actingAs($this->manager)->get(route('manage.accounts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Accounts/Index')
            ->has('accounts', 2)
            ->has('households', 2));
});

// S44, S10 — The accounts page shows roles and leaves granting and removing to the roles screen

it('lists an account with Manager and Catalogue together and answers 404 on the retired role routes (S10)', function () {
    $anna = memberOf($this->householdId);
    DB::table('account_roles')->insert([
        ['user_id' => $anna->id, 'role' => 'manager'],
        ['user_id' => $anna->id, 'role' => 'catalogue'],
    ]);

    $this->actingAs($this->manager)->get(route('manage.accounts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts', function ($accounts) use ($anna) {
                $roles = collect($accounts)->firstWhere('accountId', $anna->id)['roles'];
                sort($roles);

                return $roles === ['catalogue', 'manager'];
            }));

    foreach (['put', 'delete'] as $method) {
        foreach (['manager-role', 'finance-role'] as $role) {
            $this->actingAs($this->manager)->{$method}("/manage/accounts/{$anna->id}/{$role}")->assertNotFound();
        }
    }
    expect(DB::table('account_roles')->where('user_id', $anna->id)->count())->toBe(2);
});

// S59 — Deactivating an account (R36b)

it('rejects deactivating the only accepted manager while the other manager is pending (D2)', function () {
    $ernest = accountWith(Role::Manager);
    $ernest->forceFill(['activated_at' => null])->save();

    $this->actingAs($this->manager)
        ->from(route('manage.accounts.index'))
        ->post(route('manage.accounts.deactivation.store', $this->manager->id))
        ->assertSessionHasErrors('domain');
    expect($this->manager->fresh()->deactivated_at)->toBeNull();
});

it('rejects deactivating the only manager and deactivates and reactivates a member (S59)', function () {
    $anna = memberOf($this->householdId);

    $this->actingAs($this->manager)
        ->from(route('manage.accounts.index'))
        ->post(route('manage.accounts.deactivation.store', $this->manager->id))
        ->assertSessionHasErrors('domain');
    expect($this->manager->fresh()->deactivated_at)->toBeNull();

    $this->actingAs($this->manager)->post(route('manage.accounts.deactivation.store', $anna->id))->assertRedirect();
    expect($anna->fresh()->deactivated_at)->not->toBeNull()
        ->and($anna->fresh()->household_id)->toBeNull();

    $this->actingAs($this->manager)->delete(route('manage.accounts.deactivation.destroy', $anna->id))->assertRedirect();
    expect($anna->fresh()->deactivated_at)->toBeNull();
});

it('refuses password login to a deactivated account (R36b)', function () {
    $anna = memberOf($this->householdId);
    $this->actingAs($this->manager)->post(route('manage.accounts.deactivation.store', $anna->id));
    auth()->logout();

    $this->post(route('login.store'), ['email' => $anna->email, 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();
});

it('refuses passkey login to a deactivated account (R36b, T06 note)', function () {
    $anna = memberOf($this->householdId);
    $passkey = new Passkey;
    $passkey->setRelation('user', $anna);

    expect(Passkeys::allowsLogin(Request::create('/'), $passkey))->toBeTrue();

    $this->actingAs($this->manager)->post(route('manage.accounts.deactivation.store', $anna->id));

    expect(Passkeys::allowsLogin(Request::create('/'), $passkey))->toBeFalse()
        ->and(app(CanLogIn::class)($anna->id))->toBeFalse();
});

it('ends the session of an account that is deactivated while logged in (R36b)', function () {
    $anna = memberOf($this->householdId);
    $this->actingAs($anna)->get(route('dashboard'))->assertOk();

    DB::table('users')->where('id', $anna->id)->update(['deactivated_at' => now()]);

    $this->actingAs($anna->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('answers 403 to everyone but managers on the account pages (R33)', function () {
    $anna = memberOf($this->householdId);

    $this->actingAs($anna)->get(route('manage.accounts.index'))->assertForbidden();
    $this->actingAs($anna)->post(route('manage.accounts.deactivation.store', $this->manager->id))->assertForbidden();
    $this->actingAs($anna)->delete(route('manage.accounts.household.destroy', $this->manager->id))->assertForbidden();
});

it('offers the other households when a member is moved from the household page (S43)', function () {
    memberOf($this->householdId);

    $this->actingAs($this->manager)->get(route('manage.households.show', $this->householdId))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Households/Show')
            ->has('otherHouseholds', 1)
            ->where('otherHouseholds.0.name', 'Household J'));
});

// Membership is the household link (R35)

it('lets a manager linked to a household reach the member area and lets an unlinked account with no role not log in', function () {
    $carla = accountWith(Role::Manager);
    $anna = memberOf($this->householdId);

    $this->actingAs($this->manager)
        ->put(route('manage.accounts.household.update', $carla->id), ['household_id' => $this->householdId])
        ->assertRedirect();

    $this->actingAs($carla->fresh())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.roles', ['manager', 'member'])
            ->has('roleAreas', 2));

    $this->actingAs($this->manager)->delete(route('manage.accounts.household.destroy', $anna->id));
    auth()->logout();

    $this->post(route('login.store'), ['email' => $anna->email, 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();

    $this->actingAs($this->manager)
        ->put(route('manage.accounts.household.update', $anna->id), ['household_id' => $this->householdId]);
    auth()->logout();

    $this->post(route('login.store'), ['email' => $anna->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($anna);
});

it('drops the stored member role in the migration and keeps manager and producer roles', function () {
    $user = accountWith(Role::Manager, Role::Producer);
    // The row is legacy data from before the role CHECK constraint (D6), which no longer admits `member`.
    DB::statement('alter table account_roles drop constraint account_roles_role_check');
    DB::table('account_roles')->insert(['user_id' => $user->id, 'role' => 'member']);

    (require database_path('migrations/2026_10_23_000000_drop_member_role_from_account_roles.php'))->up();

    expect(DB::table('account_roles')->where('user_id', $user->id)->orderBy('role')->pluck('role')->all())->toBe(['manager', 'producer']);
});
