<?php

declare(strict_types=1);

use App\Actions\AddCommissionMember;
use App\Actions\AssignCommissionRole;
use App\Actions\SaveCommission;
use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function hardenMember(string $name = 'Anna Puig'): Member
{
    $user = User::factory()->member(Household::factory()->create())->create(['name' => $name, 'email' => fake()->unique()->safeEmail()]);

    return $user->member->fresh();
}

function hardenAdministrator(): User
{
    return User::factory()->administrator()->create();
}

// Names

test('commission names collide ignoring case and spaces at both ends', function () {
    Commission::factory()->create(['name' => 'Tresoreria']);

    $this->actingAs(hardenAdministrator())->post(route('commissions.store'), ['name' => ' tresoreria '])->assertSessionHasErrors('name');
    $this->actingAs(hardenAdministrator())->post(route('commissions.store'), ['name' => 'TRESORERIA'])->assertSessionHasErrors('name');

    expect(Commission::count())->toBe(1);
});

test('renaming collides ignoring case and spaces too', function () {
    Commission::factory()->create(['name' => 'Tresoreria']);
    $other = Commission::factory()->create(['name' => 'Caixes']);

    $this->actingAs(hardenAdministrator())->patch(route('commissions.update', $other), ['name' => ' tresoreria '])->assertSessionHasErrors('name');

    expect($other->fresh()->name)->toBe('Caixes');
});

test('a commission may change only the case of its own name and is stored trimmed', function () {
    $commission = Commission::factory()->create(['name' => 'Tresoreria']);

    $this->actingAs(hardenAdministrator())
        ->patch(route('commissions.update', $commission), ['name' => '  TRESORERIA '])
        ->assertSessionHasNoErrors();

    expect($commission->fresh()->name)->toBe('TRESORERIA');
});

// Deleting vs adding members

test('a member added before the deletion makes it fail with the has-members error', function () {
    $commission = Commission::factory()->create();

    $this->actingAs(hardenAdministrator())->post(route('commissions.members.store', $commission), ['member_id' => hardenMember()->id]);
    $this->actingAs(hardenAdministrator())
        ->delete(route('commissions.destroy', $commission))
        ->assertSessionHasErrors(['commission' => __('A commission with members cannot be deleted.')]);

    expect($commission->fresh())->not->toBeNull()
        ->and($commission->members()->count())->toBe(1);
});

test('adding a member after the deletion finds no commission and creates nothing', function () {
    $commission = Commission::factory()->create();
    $member = hardenMember();

    $this->actingAs(hardenAdministrator())->delete(route('commissions.destroy', $commission))->assertRedirect(route('commissions.index'));
    $this->actingAs(hardenAdministrator())
        ->post(route('commissions.members.store', $commission), ['member_id' => $member->id])
        ->assertNotFound();

    expect(DB::table('commission_member')->count())->toBe(0);
});

test('deleting and adding a member lock the commission row', function () {
    $commission = Commission::factory()->create();
    $member = hardenMember();
    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = $query->sql;
    });

    $this->actingAs(hardenAdministrator())->post(route('commissions.members.store', $commission), ['member_id' => $member->id]);
    $this->actingAs(hardenAdministrator())->delete(route('commissions.members.destroy', [$commission, $member]));
    $this->actingAs(hardenAdministrator())->delete(route('commissions.destroy', $commission));

    $locks = collect($statements)->filter(fn (string $sql) => str_contains($sql, 'from "commissions"') && str_contains($sql, 'for update'));

    expect($locks)->toHaveCount(2);
});

// Deleting a commission that holds roles

test('the membership role cannot delete an empty commission holding any role', function (Role $role) {
    $commission = Commission::factory()->create();
    $commission->assignRole($role);

    $this->actingAs(membershipRoleUser())->delete(route('commissions.destroy', $commission))->assertForbidden();

    expect($commission->fresh())->not->toBeNull();
})->with([Role::Orders, Role::Boxes, Role::Treasury, Role::Membership]);

test('an administrator deletes an empty commission holding roles and only its assignments go', function () {
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Orders);
    $other = Commission::factory()->create();
    $other->assignRole(Role::Orders);

    $this->actingAs(hardenAdministrator())->delete(route('commissions.destroy', $commission))->assertRedirect(route('commissions.index'));

    expect(Commission::count())->toBe(1)
        ->and(rolesOf($other))->toBe([Role::Orders])
        ->and(DB::table('commission_role')->count())->toBe(1);
});

test('the membership role deletes an empty commission with no roles', function () {
    $commission = Commission::factory()->create();

    $this->actingAs(membershipRoleUser())->delete(route('commissions.destroy', $commission))->assertRedirect(route('commissions.index'));

    expect(Commission::query()->whereKey($commission->id)->exists())->toBeFalse();
});

test('the page offers the delete action only to who may use it', function () {
    $empty = Commission::factory()->create(['name' => 'Buida']);
    $withRole = Commission::factory()->create(['name' => 'Amb rol']);
    $withRole->assignRole(Role::Boxes);

    $this->actingAs(membershipRoleUser())->get(route('commissions.index'))->assertInertia(fn (Assert $page) => $page
        ->where('commissions', fn ($commissions) => collect($commissions)->firstWhere('id', $empty->id)['can_delete'] === true
            && collect($commissions)->firstWhere('id', $withRole->id)['can_delete'] === false));

    $this->actingAs(hardenAdministrator())->get(route('commissions.index'))->assertInertia(fn (Assert $page) => $page
        ->where('commissions', fn ($commissions) => collect($commissions)->firstWhere('id', $withRole->id)['can_delete'] === true));
});

// Candidates and idempotence

test('each commission lists as candidates the members it does not have', function () {
    $in = hardenMember('Anna Puig');
    $out = hardenMember('Berta Soler');
    $commission = Commission::factory()->create(['name' => 'A']);
    Commission::factory()->create(['name' => 'B']);
    $commission->members()->attach($in);
    $idsOf = fn (array $candidates) => collect($candidates)->pluck('id');

    $this->actingAs(hardenAdministrator())
        ->get(route('commissions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('commissions.0.candidates', fn ($candidates) => $idsOf($candidates->all())->contains($out->id) && ! $idsOf($candidates->all())->contains($in->id))
            ->where('commissions.1.candidates', fn ($candidates) => $idsOf($candidates->all())->contains($out->id) && $idsOf($candidates->all())->contains($in->id)));
});

test('viewers who cannot manage get no candidates', function () {
    Commission::factory()->create();

    $this->actingAs(hardenMember()->user)
        ->get(route('commissions.index'))
        ->assertInertia(fn (Assert $page) => $page->where('commissions.0.candidates', []));
});

test('removing a member who is not in the commission changes nothing and is not an error', function () {
    $commission = Commission::factory()->create();
    $inside = hardenMember('Anna Puig');
    $commission->members()->attach($inside);

    $this->actingAs(hardenAdministrator())
        ->delete(route('commissions.members.destroy', [$commission, hardenMember('Berta Soler')]))
        ->assertRedirect(route('commissions.index'))
        ->assertSessionHasNoErrors();

    expect($commission->members()->count())->toBe(1);
});

test('the membership role removing a non-member of a membership commission is a quiet no-op', function () {
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);

    $this->actingAs(membershipRoleUser())
        ->delete(route('commissions.members.destroy', [$commission, hardenMember()]))
        ->assertRedirect(route('commissions.index'));
});

test('assigning a role it holds and removing one it lacks change nothing and are not errors', function () {
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Orders);
    $administrator = hardenAdministrator();

    $this->actingAs($administrator)->post(route('commissions.roles.store', $commission), ['role' => 'orders'])->assertRedirect(route('commissions.index'))->assertSessionHasNoErrors();
    $this->actingAs($administrator)->delete(route('commissions.roles.destroy', [$commission, 'boxes']))->assertRedirect(route('commissions.index'))->assertSessionHasNoErrors();

    expect(rolesOf($commission))->toBe([Role::Orders]);
});

test('the membership role cannot assign or remove roles', function () {
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Orders);
    $user = membershipRoleUser();

    $this->actingAs($user)->post(route('commissions.roles.store', $commission), ['role' => 'boxes'])->assertForbidden();
    $this->actingAs($user)->delete(route('commissions.roles.destroy', [$commission, 'orders']))->assertForbidden();

    expect(rolesOf($commission))->toBe([Role::Orders]);
});

// Direct action calls (bypassing the form requests)

test('the add action finds no commission once the row was deleted', function () {
    $commission = Commission::factory()->create();
    $member = hardenMember();
    $commission->delete();

    expect(fn () => resolve(AddCommissionMember::class)->handle($commission, $member))->toThrow(ModelNotFoundException::class);
    expect(DB::table('commission_member')->count())->toBe(0);
});

test('a name differing only in case loses to the unique index with a validation error', function () {
    Commission::factory()->create(['name' => 'Tresoreria']);

    expect(fn () => resolve(SaveCommission::class)->handle('TRESORERIA'))->toThrow(function (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    });
    expect(Commission::count())->toBe(1);
});

test('renaming to a name differing only in case loses to the unique index with a validation error', function () {
    Commission::factory()->create(['name' => 'Tresoreria']);
    $other = Commission::factory()->create(['name' => 'Caixes']);

    expect(fn () => resolve(SaveCommission::class)->handle('tresoreria', $other))->toThrow(function (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    });
    expect($other->fresh()->name)->toBe('Caixes');
});

test('assigning a role locks the commission row', function () {
    $commission = Commission::factory()->create();
    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = $query->sql;
    });

    $this->actingAs(hardenAdministrator())->post(route('commissions.roles.store', $commission), ['role' => 'orders']);

    $locks = collect($statements)->filter(fn (string $sql) => str_contains($sql, 'from "commissions"') && str_contains($sql, 'for update'));

    expect($locks)->toHaveCount(1);
});

test('assigning a role to a commission deleted meanwhile finds no commission and creates nothing', function () {
    $commission = Commission::factory()->create();
    $commission->delete();

    expect(fn () => resolve(AssignCommissionRole::class)->handle($commission, Role::Orders))->toThrow(ModelNotFoundException::class);
    expect(DB::table('commission_role')->count())->toBe(0);
});
