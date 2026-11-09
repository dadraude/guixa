<?php

use App\Models\User;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function holderOf(Role $role, array $attributes = []): User
{
    $user = accountWith($role);
    $user->forceFill($attributes)->save();

    return $user;
}

it('keeps the role screen and its grant route to managers (R1, R9)', function (string $method, string $uri) {
    $this->{$method}($uri)->assertRedirect(route('login'));

    foreach ([[Role::Finance], [Role::Catalogue], [Role::Receiver], [Role::Producer], [Role::Member], []] as $roles) {
        $this->actingAs(accountWith(...$roles))->{$method}($uri)->assertForbidden();
    }
})->with([
    ['get', '/manage/roles'],
    ['post', '/manage/roles/catalogue/holders'],
    ['delete', '/manage/roles/catalogue/holders/00000000-0000-7000-8000-000000000001'],
]);

it('answers 404 when the role to grant is not a staff role (R1)', function (string $role) {
    $anna = accountWith();

    $this->actingAs(accountWith(Role::Manager))
        ->post("/manage/roles/{$role}/holders", ['account_id' => $anna->id])
        ->assertNotFound();

    expect(DB::table('account_roles')->where('user_id', $anna->id)->exists())->toBeFalse();
})->with(['producer', 'member', 'unknown']);

it('answers 404 before validating the body when the role is not a staff role (R1)', function (string $role, array $body) {
    $this->actingAs(accountWith(Role::Manager))
        ->from('/manage/roles')
        ->post("/manage/roles/{$role}/holders", $body)
        ->assertNotFound();
})->with([
    'producer, no body' => ['producer', []],
    'member, not a uuid' => ['member', ['account_id' => 'nope']],
    'unknown, no body' => ['unknown', []],
    'uuid-like name' => ['00000000-0000-7000-8000-00000000abcd', ['account_id' => '']],
]);

it('lists the four staff groups in order with their holders (R9, S2)', function () {
    $manager = accountWith(Role::Manager);
    $carla = holderOf(Role::Receiver, ['name' => 'Carla', 'deactivated_at' => now()]);

    $this->actingAs($manager)->get('/manage/roles')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Roles/Index')
            ->has('groups', 4)
            ->where('groups.0.role', 'manager')
            ->where('groups.1.role', 'finance')
            ->where('groups.2.role', 'catalogue')
            ->where('groups.3.role', 'receiver')
            ->where('groups.0.holders.0.accountId', $manager->id)
            ->where('groups.0.holders.0.isSelf', true)
            ->where('groups.0.holders.0.canRemove', false)
            ->where('groups.0.holders.0.removeBlockedReason', 'És el darrer gestor actiu: cal que un altre compte tingui el rol de gestor i hagi acceptat la invitació.')
            ->has('groups.1.holders', 0)
            ->where('groups.3.holders.0.accountId', $carla->id)
            ->where('groups.3.holders.0.status', 'deactivated')
            ->where('groups.3.holders.0.isSelf', false));
});

it('loads the candidates of one group as a deferred prop and marks their status (R10, S2)', function () {
    // The factory's random name and email would decide where the manager sorts and whether it matches.
    $this->actingAs(holderOf(Role::Manager, ['name' => 'Zed Manager', 'email' => 'zed@example.org']));
    $bernat = accountWith();
    $bernat->forceFill(['name' => 'Bernat', 'email' => 'bernat@example.org', 'activated_at' => null])->save();
    DB::table('invitations')->insert(['account_id' => $bernat->id, 'token_hash' => 'h', 'sent_at' => now()]);
    $dora = accountWith();
    $dora->forceFill(['name' => 'Dora', 'email' => 'dora@example.org', 'activated_at' => null])->save();
    DB::table('invitations')->insert(['account_id' => $dora->id, 'token_hash' => 'd', 'sent_at' => now()->subDays(30)]);
    $carla = accountWith();
    $carla->forceFill(['name' => 'Carla', 'email' => 'carla@example.org', 'deactivated_at' => now()])->save();

    $this->get('/manage/roles?role=finance&q=a')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('candidates')
            ->loadDeferredProps(fn (Assert $loaded) => $loaded
                ->has('candidates', 3)
                ->where('candidates.0.name', 'Bernat')
                ->where('candidates.0.status', 'invitation_pending')
                ->where('candidates.1.name', 'Dora')
                ->where('candidates.1.status', 'invitation_expired')
                ->where('candidates.2.name', 'Zed Manager')
                ->where('candidates.2.status', 'active')));
});

it('loads no candidates without a group and a query, or for a role that is not a staff role', function (string $query) {
    $this->actingAs(accountWith(Role::Manager));
    accountWith();

    $this->get("/manage/roles{$query}")
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $loaded) => $loaded->has('candidates', 0)));
})->with(['', '?role=finance', '?q=a', '?role=producer&q=a', '?role=nope&q=a']);

it('grants the role, records it and leaves the holder listed (S1, R2a)', function () {
    $manager = accountWith(Role::Manager);
    $anna = accountWith();

    $this->actingAs($manager)->from('/manage/roles')
        ->post('/manage/roles/catalogue/holders', ['account_id' => $anna->id])
        ->assertRedirect('/manage/roles');

    expect(DB::table('account_roles')->where('user_id', $anna->id)->pluck('role')->all())->toBe(['catalogue']);
    $change = DB::table('role_changes')->sole();
    expect([$change->user_id, $change->role, $change->change, $change->actor_id])
        ->toBe([$anna->id, 'catalogue', 'granted', $manager->id]);
    $this->get('/manage/roles')->assertInertia(fn (Assert $page) => $page
        ->where('groups.2.holders.0.accountId', $anna->id));
});

it('rejects a grant that is already held with the reason and records nothing more (R13)', function () {
    $manager = accountWith(Role::Manager);
    $anna = accountWith(Role::Catalogue);

    $this->actingAs($manager)->from('/manage/roles')
        ->post('/manage/roles/catalogue/holders', ['account_id' => $anna->id])
        ->assertRedirect('/manage/roles')
        ->assertSessionHasErrors(['domain' => 'Aquest compte ja té aquest rol.']);

    expect(DB::table('role_changes')->count())->toBe(0);
});

it('rejects a grant to a deactivated account and an unknown account (R2, R13)', function () {
    $manager = accountWith(Role::Manager);
    $carla = accountWith();
    $carla->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($manager)->from('/manage/roles')
        ->post('/manage/roles/finance/holders', ['account_id' => $carla->id])
        ->assertSessionHasErrors(['domain' => 'Aquest compte està desactivat.']);
    $this->from('/manage/roles')
        ->post('/manage/roles/finance/holders', ['account_id' => (string) Str::uuid7()])
        ->assertSessionHasErrors('domain');
    $this->from('/manage/roles')
        ->post('/manage/roles/finance/holders', ['account_id' => 'not-a-uuid'])
        ->assertSessionHasErrors('account_id');

    expect(DB::table('role_changes')->count())->toBe(0);
});

it('removes a role, records it and lists the holder no more (S9, R11)', function () {
    $manager = accountWith(Role::Manager);
    $anna = accountWith(Role::Finance);

    $this->actingAs($manager)->from('/manage/roles')
        ->delete("/manage/roles/finance/holders/{$anna->id}")
        ->assertRedirect('/manage/roles');

    expect(DB::table('account_roles')->where('user_id', $anna->id)->exists())->toBeFalse();
    $change = DB::table('role_changes')->sole();
    expect([$change->user_id, $change->role, $change->change, $change->actor_id])
        ->toBe([$anna->id, 'finance', 'removed', $manager->id]);
    $this->get('/manage/roles')->assertInertia(fn (Assert $page) => $page->has('groups.1.holders', 0));
});

it('tells the second removal that the role is no longer held and records nothing more (S9, R13)', function () {
    $manager = accountWith(Role::Manager);
    $anna = accountWith(Role::Finance);
    $this->actingAs($manager)->delete("/manage/roles/finance/holders/{$anna->id}");

    $this->from('/manage/roles')
        ->delete("/manage/roles/finance/holders/{$anna->id}")
        ->assertSessionHasErrors(['domain' => 'Aquest compte ja no té aquest rol.']);

    expect(DB::table('role_changes')->count())->toBe(1);
});

it('removes Receiver from a deactivated account and keeps its other roles on reactivation (S2, R2)', function () {
    $manager = accountWith(Role::Manager);
    $carla = holderOf(Role::Receiver, ['deactivated_at' => now()]);
    DB::table('account_roles')->insert(['user_id' => $carla->id, 'role' => 'finance']);

    $this->actingAs($manager)->delete("/manage/roles/receiver/holders/{$carla->id}")->assertRedirect();
    $this->delete(route('manage.accounts.deactivation.destroy', $carla->id))->assertRedirect();

    expect(DB::table('account_roles')->where('user_id', $carla->id)->pluck('role')->all())->toBe(['finance']);
    expect($carla->fresh()->deactivated_at)->toBeNull();
});

it('rejects removing Manager from the only accepted manager while the other is pending and disables the action (R1, S3)', function () {
    $dolors = accountWith(Role::Manager);
    $ernest = holderOf(Role::Manager, ['activated_at' => null]);

    $this->actingAs($dolors)->from('/manage/roles')
        ->delete("/manage/roles/manager/holders/{$dolors->id}")
        ->assertSessionHasErrors(['domain' => 'No es pot retirar el rol del darrer gestor actiu.']);

    expect(DB::table('role_changes')->count())->toBe(0);
    $this->get('/manage/roles')->assertInertia(fn (Assert $page) => $page
        ->where('groups.0.holders', fn ($holders) => collect($holders)->firstWhere('accountId', $dolors->id)['canRemove'] === false
            && collect($holders)->firstWhere('accountId', $ernest->id)['canRemove'] === true));
});

it('redirects a manager who removed their own Manager role to the dashboard, where /manage/roles is 403 (S3)', function () {
    $dolors = accountWith(Role::Manager);
    holderOf(Role::Manager);

    $this->actingAs($dolors)->from('/manage/roles')
        ->delete("/manage/roles/manager/holders/{$dolors->id}")
        ->assertRedirect(route('dashboard'));

    $this->get('/manage/roles')->assertForbidden();
    expect(DB::table('role_changes')->count())->toBe(1);
});

it('answers 404 when the role to remove is not a staff role (R1)', function (string $role) {
    $anna = accountWith(Role::Producer);

    $this->actingAs(accountWith(Role::Manager))
        ->delete("/manage/roles/{$role}/holders/{$anna->id}")
        ->assertNotFound();

    expect(DB::table('role_changes')->count())->toBe(0);
})->with(['producer', 'member', 'unknown']);
