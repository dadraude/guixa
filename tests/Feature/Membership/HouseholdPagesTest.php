<?php

use App\Models\User;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function accountWithRole(Role $role): User
{
    $user = User::factory()->create();
    DB::table('account_roles')->insert(['user_id' => $user->id, 'role' => $role->value]);

    return $user;
}

it('lets a manager create a household, add a member and see the pending invitation', function () {
    Mail::fake();
    $manager = accountWithRole(Role::Manager);

    $response = $this->actingAs($manager)->post(route('manage.households.store'), ['name' => 'Household H']);
    $householdId = DB::table('households')->value('id');
    $response->assertRedirect(route('manage.households.show', $householdId));

    $this->actingAs($manager)
        ->post(route('manage.households.members.store', $householdId), ['name' => 'Anna', 'email' => 'anna@example.org'])
        ->assertRedirect();

    $this->actingAs($manager)->get(route('manage.households.show', $householdId))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Households/Show')
            ->where('household.name', 'Household H')
            ->where('household.members.0.email', 'anna@example.org')
            ->where('household.members.0.invitationPending', true));

    $this->actingAs($manager)->get(route('manage.households.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Households/Index')
            ->where('households.0.name', 'Household H')
            ->where('households.0.memberCount', 1));
});

it('renames a household', function () {
    $manager = accountWithRole(Role::Manager);
    $this->actingAs($manager)->post(route('manage.households.store'), ['name' => 'Household H']);
    $householdId = DB::table('households')->value('id');

    $this->actingAs($manager)->put(route('manage.households.update', $householdId), ['name' => 'Casa Roca'])->assertRedirect();

    expect(DB::table('households')->value('name'))->toBe('Casa Roca');
});

it('shows a domain error as a form error, e.g. an email already in use', function () {
    Mail::fake();
    $manager = accountWithRole(Role::Manager);
    $this->actingAs($manager)->post(route('manage.households.store'), ['name' => 'Household H']);
    $householdId = DB::table('households')->value('id');

    $this->actingAs($manager)
        ->from(route('manage.households.show', $householdId))
        ->post(route('manage.households.members.store', $householdId), ['name' => 'Anna', 'email' => $manager->email])
        ->assertSessionHasErrors('domain');
});

it('answers 404 for an unknown household and 403 to everyone but managers (S56)', function () {
    $manager = accountWithRole(Role::Manager);
    $member = accountWith(Role::Member);
    $unknown = '00000000-0000-7000-8000-000000000099';

    $this->actingAs($manager)->get(route('manage.households.show', $unknown))->assertNotFound();
    $this->actingAs($member)->get(route('manage.households.index'))->assertForbidden();
    $this->actingAs($member)->post(route('manage.households.store'), ['name' => 'Sneaky'])->assertForbidden();
    $this->actingAs($member)->post(route('manage.households.members.store', $unknown), ['name' => 'B', 'email' => 'b@example.org'])->assertForbidden();
    expect(DB::table('households')->count())->toBe(1); // only the member's own
});

it('sends guests to the login page', function () {
    $this->get(route('manage.households.index'))->assertRedirect(route('login'));
});

it('shows the settlement warning, deactivates with a separate action, and lists outstanding households (R35a, S58)', function () {
    $manager = accountWithRole(Role::Manager);
    $this->actingAs($manager)->post(route('manage.households.store'), ['name' => 'Household H']);
    $householdId = DB::table('households')->value('id');
    $this->actingAs($manager)->post(route('manage.households.wallet.top-ups.store', $householdId), ['amount' => '5.00']);

    $this->actingAs($manager)->get(route('manage.households.show', $householdId))
        ->assertInertia(fn (Assert $page) => $page
            ->where('deactivationWarning.balanceCents', 500)
            ->where('deactivationWarning.openBaskets', []));

    $this->actingAs($manager)->post(route('manage.households.deactivate', $householdId))->assertRedirect();

    $this->actingAs($manager)->get(route('manage.households.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('toSettle.0.householdId', $householdId)
            ->where('toSettle.0.balanceCents', 500));

    $this->actingAs($manager)->post(route('manage.households.reactivate', $householdId))->assertRedirect();
    $this->actingAs($manager)->get(route('manage.households.show', $householdId))
        ->assertInertia(fn (Assert $page) => $page->where('household.active', true));
});

it('keeps open baskets and wallet adjustments available after deactivation (R35a, S58)', function () {
    $manager = accountWithRole(Role::Manager);
    $this->actingAs($manager)->post(route('manage.households.store'), ['name' => 'Household H']);
    $householdId = DB::table('households')->value('id');
    $cycleId = (string) Str::uuid7();
    $orderId = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $cycleId, 'pickup_date' => '2026-10-28', 'settled_at' => now()]);
    DB::table('orders')->insert(['id' => $orderId, 'cycle_id' => $cycleId, 'household_id' => $householdId]);
    $basketId = (string) Str::uuid7();
    DB::table('baskets')->insert([
        'id' => $basketId, 'household_id' => $householdId, 'household_name' => 'Household H', 'kind' => 'weekly', 'state' => 'draft',
        'created_by' => null, 'created_at' => now(), 'source_order_id' => $orderId, 'reference_cycle_id' => $cycleId,
    ]);

    $this->actingAs($manager)->get(route('manage.households.show', $householdId))
        ->assertInertia(fn (Assert $page) => $page
            ->where('deactivationWarning.openBaskets.0.basketId', $basketId)
            ->where('deactivationWarning.openBaskets.0.status', 'draft'));

    $this->actingAs($manager)->post(route('manage.households.deactivate', $householdId))->assertRedirect();
    expect(DB::table('baskets')->where('id', $basketId)->value('state'))->toBe('draft');
    $this->actingAs($manager)->post(route('manage.households.wallet.top-ups.store', $householdId), ['amount' => '5.00'])->assertRedirect();
    $receiptId = DB::table('wallet_movements')->where('household_id', $householdId)->where('type', 'top_up')->value('id');
    $this->actingAs($manager)->post(route('manage.households.wallet.corrections.store', $householdId), [
        'amount' => '-5.00',
        'note' => 'settled',
        'source_movement_id' => $receiptId,
        'correction_id' => (string) Str::uuid7(),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($manager)->get(route('manage.households.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('toSettle.0.balanceCents', 0)
            ->where('toSettle.0.openBaskets.0.basketId', $basketId));

    DB::table('baskets')->where('id', $basketId)->update(['state' => 'collected']);
    $this->actingAs($manager)->get(route('manage.households.index'))
        ->assertInertia(fn (Assert $page) => $page->where('toSettle', []));
});
