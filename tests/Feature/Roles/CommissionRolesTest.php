<?php

declare(strict_types=1);

use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function activeMember(string $name = 'Anna Puig'): Member
{
    $user = User::factory()->member(Household::factory()->create())->create(['name' => $name, 'email' => fake()->unique()->safeEmail()]);
    $user->member->update(['is_active' => true]);

    return $user->member->fresh();
}

function commissionWithRoles(string $name, Role ...$roles): Commission
{
    $commission = Commission::factory()->create(['name' => $name]);
    foreach ($roles as $role) {
        $commission->assignRole($role);
    }

    return $commission;
}

test('an administrator assigns several roles to a commission and a role to several commissions', function () {
    $administrator = User::factory()->administrator()->create();
    $orders = Commission::factory()->create();
    $boxes = Commission::factory()->create();

    $this->actingAs($administrator)->post(route('commissions.roles.store', $orders), ['role' => 'orders'])->assertRedirect(route('commissions.index'));
    $this->actingAs($administrator)->post(route('commissions.roles.store', $orders), ['role' => 'boxes']);
    $this->actingAs($administrator)->post(route('commissions.roles.store', $boxes), ['role' => 'boxes']);

    expect($orders->roles())->toEqualCanonicalizing([Role::Orders, Role::Boxes])
        ->and($boxes->roles())->toBe([Role::Boxes]);
});

test('assigning the same role twice keeps a single assignment', function () {
    $administrator = User::factory()->administrator()->create();
    $commission = Commission::factory()->create();

    $this->actingAs($administrator)->post(route('commissions.roles.store', $commission), ['role' => 'orders']);
    $this->actingAs($administrator)->post(route('commissions.roles.store', $commission), ['role' => 'orders']);

    expect(DB::table('commission_role')->count())->toBe(1);
});

test('only the fixed roles can be assigned', function () {
    $administrator = User::factory()->administrator()->create();
    $commission = Commission::factory()->create();

    $this->actingAs($administrator)->post(route('commissions.roles.store', $commission), ['role' => 'cleaning'])->assertSessionHasErrors('role');
    $this->actingAs($administrator)->post(route('commissions.roles.store', $commission), [])->assertSessionHasErrors('role');

    expect($commission->roles())->toBe([]);
});

test('an administrator removes a role from a commission and the others keep theirs', function () {
    $administrator = User::factory()->administrator()->create();
    $commission = commissionWithRoles('Comandes', Role::Orders, Role::Boxes);

    $this->actingAs($administrator)
        ->delete(route('commissions.roles.destroy', [$commission, 'orders']))
        ->assertRedirect(route('commissions.index'));

    expect($commission->roles())->toBe([Role::Boxes]);
});

test('a member with the membership role cannot assign or remove roles', function () {
    $member = activeMember();
    $membership = commissionWithRoles('Membres', Role::Membership);
    $membership->members()->attach($member);
    $other = commissionWithRoles('Comandes', Role::Orders);

    $this->actingAs($member->user)->post(route('commissions.roles.store', $other), ['role' => 'treasury'])->assertForbidden();
    $this->actingAs($member->user)->delete(route('commissions.roles.destroy', [$other, 'orders']))->assertForbidden();
    $this->actingAs($member->user)->post(route('commissions.roles.store', $membership), ['role' => 'treasury'])->assertForbidden();

    expect($other->roles())->toBe([Role::Orders])
        ->and($membership->roles())->toBe([Role::Membership]);
});

test('deleting an empty commission removes only its role assignments', function () {
    $administrator = User::factory()->administrator()->create();
    $doomed = commissionWithRoles('Neteja', Role::Orders, Role::Boxes);
    $kept = commissionWithRoles('Comandes', Role::Orders);

    $this->actingAs($administrator)->delete(route('commissions.destroy', $doomed))->assertRedirect(route('commissions.index'));

    expect(DB::table('commission_role')->pluck('commission_id')->unique()->all())->toBe([$kept->id])
        ->and($kept->roles())->toBe([Role::Orders]);
});

test('the commission list shows each commissions roles and who may assign them', function () {
    $administrator = User::factory()->administrator()->create();
    commissionWithRoles('Comandes', Role::Orders, Role::Membership);

    $this->actingAs($administrator)
        ->get(route('commissions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canAssignRoles', true)
            ->where('commissions.0.roles', [
                ['value' => 'orders', 'label' => 'Comandes'],
                ['value' => 'membership', 'label' => 'Membres'],
            ])
            ->has('availableRoles', 4)
            ->where('availableRoles.0', ['value' => 'orders', 'label' => 'Comandes']));
});

test('a member sees their commissions and the roles those give them', function () {
    $member = activeMember();
    commissionWithRoles('Caixes i comandes', Role::Orders, Role::Boxes)->members()->attach($member);
    commissionWithRoles('Neteja')->members()->attach($member);
    commissionWithRoles('Tresoreria', Role::Treasury);

    $this->actingAs($member->user)
        ->get(route('commissions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canAssignRoles', false)
            ->has('mine', 2)
            ->where('mine.0.name', 'Caixes i comandes')
            ->where('mine.0.roles', [
                ['value' => 'orders', 'label' => 'Comandes'],
                ['value' => 'boxes', 'label' => 'Caixes'],
            ])
            ->where('mine.1.name', 'Neteja')
            ->where('mine.1.roles', []));
});

test('role names are in Catalan', function () {
    expect(Role::Orders->label())->toBe('Comandes')
        ->and(Role::Boxes->label())->toBe('Caixes')
        ->and(Role::Treasury->label())->toBe('Tresoreria')
        ->and(Role::Membership->label())->toBe('Membres');

    foreach (['Roles', 'Add role', 'Your commissions', 'No roles'] as $key) {
        expect(__($key))->not->toBe($key);
    }
});

test('the home page shows the member their commissions and the roles those give them', function () {
    $member = activeMember();
    commissionWithRoles('Caixes i comandes', Role::Orders, Role::Boxes)->members()->attach($member);
    commissionWithRoles('Neteja')->members()->attach($member);
    commissionWithRoles('Tresoreria', Role::Treasury);

    $this->actingAs($member->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('commissions', 2)
            ->where('commissions.0.name', 'Caixes i comandes')
            ->where('commissions.0.roles', [
                ['value' => 'orders', 'label' => 'Comandes'],
                ['value' => 'boxes', 'label' => 'Caixes'],
            ])
            ->where('commissions.1.name', 'Neteja')
            ->where('commissions.1.roles', []));
});

test('the home page has no commissions for a member who is in none', function () {
    commissionWithRoles('Tresoreria', Role::Treasury);

    $this->actingAs(activeMember()->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('commissions', []));
});
