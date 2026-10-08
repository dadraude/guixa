<?php

declare(strict_types=1);

use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function memberOf(string $name = 'Anna Puig', bool $active = true): Member
{
    $user = User::factory()->member(Household::factory()->create())->create(['name' => $name, 'email' => fake()->unique()->safeEmail()]);
    $user->member->update(['is_active' => $active]);

    return $user->member->fresh();
}

test('an administrator creates a commission with a trimmed name', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)
        ->post(route('commissions.store'), ['name' => '  Comandes  '])
        ->assertRedirect(route('commissions.index'));

    expect(Commission::sole()->name)->toBe('Comandes');
});

test('commission names are required and unique, also when renaming', function () {
    $administrator = User::factory()->administrator()->create();
    Commission::factory()->create(['name' => 'Comandes']);
    $caixes = Commission::factory()->create(['name' => 'Caixes']);

    $this->actingAs($administrator)->post(route('commissions.store'), ['name' => ''])->assertSessionHasErrors('name');
    $this->actingAs($administrator)->post(route('commissions.store'), ['name' => 'Comandes'])->assertSessionHasErrors('name');
    $this->actingAs($administrator)->patch(route('commissions.update', $caixes), ['name' => 'Comandes'])->assertSessionHasErrors('name');

    expect(Commission::count())->toBe(2)
        ->and($caixes->fresh()->name)->toBe('Caixes');
});

test('an administrator renames a commission and may keep its own name', function () {
    $administrator = User::factory()->administrator()->create();
    $commission = Commission::factory()->create(['name' => 'Comandes']);

    $this->actingAs($administrator)
        ->patch(route('commissions.update', $commission), ['name' => 'Comandes'])
        ->assertRedirect(route('commissions.index'))
        ->assertSessionHasNoErrors();
    $this->actingAs($administrator)
        ->patch(route('commissions.update', $commission), ['name' => 'Compres'])
        ->assertRedirect(route('commissions.index'));

    expect($commission->fresh()->name)->toBe('Compres');
});

test('an administrator deletes an empty commission', function () {
    $administrator = User::factory()->administrator()->create();
    $commission = Commission::factory()->create();

    $this->actingAs($administrator)
        ->delete(route('commissions.destroy', $commission))
        ->assertRedirect(route('commissions.index'));

    expect(Commission::count())->toBe(0);
});

test('deleting a commission with members is rejected', function () {
    $administrator = User::factory()->administrator()->create();
    $commission = Commission::factory()->create();
    $commission->members()->attach(memberOf());

    $this->actingAs($administrator)
        ->delete(route('commissions.destroy', $commission))
        ->assertSessionHasErrors('commission');

    expect(Commission::count())->toBe(1);
});

test('an active member is added to a commission and removed again', function () {
    $administrator = User::factory()->administrator()->create();
    $commission = Commission::factory()->create();
    $member = memberOf();

    $this->actingAs($administrator)
        ->post(route('commissions.members.store', $commission), ['member_id' => $member->id])
        ->assertRedirect(route('commissions.index'));
    expect($commission->members()->pluck('members.id')->all())->toBe([$member->id]);

    $this->actingAs($administrator)
        ->delete(route('commissions.members.destroy', [$commission, $member]))
        ->assertRedirect(route('commissions.index'));
    expect($commission->members()->count())->toBe(0);
});

test('adding the same member twice keeps a single membership', function () {
    $administrator = User::factory()->administrator()->create();
    $commission = Commission::factory()->create();
    $member = memberOf();

    $this->actingAs($administrator)->post(route('commissions.members.store', $commission), ['member_id' => $member->id]);
    $this->actingAs($administrator)->post(route('commissions.members.store', $commission), ['member_id' => $member->id]);

    expect($commission->members()->count())->toBe(1);
});

test('an inactive or unknown member cannot be added', function () {
    $administrator = User::factory()->administrator()->create();
    $commission = Commission::factory()->create();
    $inactive = memberOf('Pere Puig', active: false);

    $this->actingAs($administrator)
        ->post(route('commissions.members.store', $commission), ['member_id' => $inactive->id])
        ->assertSessionHasErrors('member_id');
    $this->actingAs($administrator)
        ->post(route('commissions.members.store', $commission), ['member_id' => fake()->uuid()])
        ->assertSessionHasErrors('member_id');

    expect($commission->members()->count())->toBe(0);
});

test('a member without administrator rights is refused every change', function () {
    $user = memberOf()->user;
    $commission = Commission::factory()->create(['name' => 'Comandes']);
    $other = memberOf('Pere Puig');
    $commission->members()->attach($other);

    $this->actingAs($user)->post(route('commissions.store'), ['name' => 'Nova'])->assertForbidden();
    $this->actingAs($user)->patch(route('commissions.update', $commission), ['name' => 'Nova'])->assertForbidden();
    $this->actingAs($user)->delete(route('commissions.destroy', $commission))->assertForbidden();
    $this->actingAs($user)->post(route('commissions.members.store', $commission), ['member_id' => $user->member->id])->assertForbidden();
    $this->actingAs($user)->delete(route('commissions.members.destroy', [$commission, $other]))->assertForbidden();

    expect(Commission::count())->toBe(1)
        ->and($commission->fresh()->name)->toBe('Comandes')
        ->and($commission->members()->count())->toBe(1);
});

test('every member sees all commissions and their members names, but no contact details', function () {
    $viewer = memberOf('Marta Vila');
    $viewer->update(['phone' => '600000000']);
    $anna = memberOf('Anna Puig');
    $anna->update(['phone' => '611111111']);
    $orders = Commission::factory()->create(['name' => 'Comandes']);
    Commission::factory()->create(['name' => 'Caixes']);
    $orders->members()->attach($anna);

    $this->actingAs($viewer->user)
        ->get(route('commissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('commissions/Index')
            ->where('canManage', false)
            ->has('commissions', 2)
            ->where('commissions.0.name', 'Caixes')
            ->where('commissions.0.members', [])
            ->where('commissions.1.name', 'Comandes')
            ->where('commissions.1.members.0.name', 'Anna Puig')
            ->missing('commissions.1.members.0.email')
            ->missing('commissions.1.members.0.phone')
            ->where('candidates', []))
        ->assertDontSee($anna->user->email)
        ->assertDontSee('611111111');
});

test('an administrator gets the active members who can be added', function () {
    // An administrator is also an active member, so they are a candidate too.
    $administrator = User::factory()->administrator()->create(['name' => 'Zoe Admin']);
    $in = memberOf('Anna Puig');
    memberOf('Pere Puig', active: false);
    memberOf('Berta Soler');
    $commission = Commission::factory()->create();
    $commission->members()->attach($in);

    $this->actingAs($administrator)
        ->get(route('commissions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManage', true)
            ->has('candidates', 3)
            ->where('candidates.0.name', 'Anna Puig')
            ->where('candidates.1.name', 'Berta Soler')
            ->where('candidates.2.name', 'Zoe Admin')
            ->missing('candidates.0.email'));
});

test('a user without a member or administrator profile cannot see the list', function () {
    $this->actingAs(User::factory()->create())->get(route('commissions.index'))->assertForbidden();
});

test('an administrator without a member profile can view the commissions', function () {
    $this->actingAs(User::factory()->administrator()->create())->get(route('commissions.index'))->assertOk();
});

test('an inactive member cannot see the commissions', function () {
    $this->actingAs(memberOf(active: false)->user)->get(route('commissions.index'))->assertRedirect(route('login'));
});

test('commission changes go through the manage-membership rule', function () {
    expect(Gate::forUser(User::factory()->create())->allows('manage-membership'))->toBeFalse();
});

test('the commission screen text is in Catalan', function () {
    foreach (['Commissions', 'Add commission', 'Add member', 'Remove', 'Delete', 'No commissions yet.'] as $key) {
        expect(__($key))->not->toBe($key);
    }
});
