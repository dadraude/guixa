<?php

declare(strict_types=1);

use App\Auth\Role;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function choiceActor(): User
{
    $user = User::factory()->member(Household::factory()->create())->create();
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($user->member->fresh());

    return $user->fresh();
}

function choiceMember(Household $household, bool $active = true, string $name = 'Pau'): Member
{
    $member = User::factory()->member($household)->create(['name' => $name])->member->fresh();
    $member->update(['is_active' => $active]);

    return $member;
}

function choiceMembershipHolder(Household $household): Member
{
    $member = choiceMember($household, true, 'Cap de Membres');
    $commission = Commission::factory()->create();
    $commission->assignRole(Role::Membership);
    $commission->members()->attach($member);

    return $member;
}

test('deactivating a household alone deactivates nobody and ends no session', function () {
    $household = Household::factory()->create();
    $member = choiceMember($household);

    $this->actingAs(choiceActor())
        ->post(route('households.deactivate', $household), ['with_members' => false])
        ->assertRedirect(route('households.index'));

    expect($household->fresh()->is_active)->toBeFalse()
        ->and($member->fresh()->is_active)->toBeTrue();
    $this->actingAs($member->user->fresh())->get(route('dashboard'))->assertRedirect(route('waiting'));
});

test('deactivating a household with its members deactivates every active member', function () {
    $household = Household::factory()->create();
    $first = choiceMember($household, true, 'Anna');
    $second = choiceMember($household, true, 'Bernat');
    $alreadyInactive = choiceMember($household, false, 'Carles');
    $elsewhere = choiceMember(Household::factory()->create());

    $this->actingAs(choiceActor())
        ->post(route('households.deactivate', $household), ['with_members' => true])
        ->assertRedirect(route('households.index'));

    expect($household->fresh()->is_active)->toBeFalse()
        ->and($first->fresh()->is_active)->toBeFalse()
        ->and($second->fresh()->is_active)->toBeFalse()
        ->and($alreadyInactive->fresh()->is_active)->toBeFalse()
        ->and($elsewhere->fresh()->is_active)->toBeTrue();
});

test('the server rejects a request without an explicit choice when there are members to affect', function () {
    $household = Household::factory()->create();
    choiceMember($household);
    $inactiveHousehold = Household::factory()->inactive()->create();
    choiceMember($inactiveHousehold, false);
    $actor = choiceActor();

    $this->actingAs($actor)->post(route('households.deactivate', $household))->assertSessionHasErrors('with_members');
    $this->actingAs($actor)->post(route('households.deactivate', $household), ['with_members' => 'maybe'])->assertSessionHasErrors('with_members');
    $this->actingAs($actor)->post(route('households.reactivate', $inactiveHousehold))->assertSessionHasErrors('with_members');

    expect($household->fresh()->is_active)->toBeTrue()
        ->and($inactiveHousehold->fresh()->is_active)->toBeFalse();
});

test('the choice is not needed when nobody would be affected', function () {
    $empty = Household::factory()->create();
    $onlyInactive = Household::factory()->create();
    choiceMember($onlyInactive, false);
    $inactiveWithActive = Household::factory()->inactive()->create();
    choiceMember($inactiveWithActive, true);
    $actor = choiceActor();

    $this->actingAs($actor)->post(route('households.deactivate', $empty))->assertSessionHasNoErrors();
    $this->actingAs($actor)->post(route('households.deactivate', $onlyInactive))->assertSessionHasNoErrors();
    $this->actingAs($actor)->post(route('households.reactivate', $inactiveWithActive))->assertSessionHasNoErrors();

    expect($empty->fresh()->is_active)->toBeFalse()
        ->and($onlyInactive->fresh()->is_active)->toBeFalse()
        ->and($inactiveWithActive->fresh()->is_active)->toBeTrue();
});

test('deactivating a household alone is allowed on one own household and the owner household', function () {
    $actor = choiceActor();
    $ownerUser = User::factory()->owner()->member(Household::factory()->create())->create();

    $this->actingAs($actor)->post(route('households.deactivate', $actor->member->household_id), ['with_members' => false])->assertRedirect();
    $this->actingAs($actor)->post(route('households.deactivate', $ownerUser->member->household_id), ['with_members' => false])->assertRedirect();

    expect($actor->member->household->fresh()->is_active)->toBeFalse()
        ->and($ownerUser->member->household->fresh()->is_active)->toBeFalse()
        ->and($ownerUser->member->fresh()->is_active)->toBeTrue();
});

test('deactivating with members skips and names the ones the actor may not deactivate', function () {
    $household = Household::factory()->create();
    $plain = choiceMember($household, true, 'Anna');
    $holder = choiceMembershipHolder($household);
    $administrator = User::factory()->administrator()->member($household)->create(['name' => 'Admina']);
    $owner = User::factory()->owner()->member($household)->create(['name' => 'Propietaria']);
    $actor = choiceActor();
    $actor->member->update(['household_id' => $household->id]);

    $this->actingAs($actor)
        ->post(route('households.deactivate', $household), ['with_members' => true])
        ->assertRedirect(route('households.index'));

    expect($household->fresh()->is_active)->toBeFalse()
        ->and($plain->fresh()->is_active)->toBeFalse()
        ->and($holder->fresh()->is_active)->toBeTrue()
        ->and($administrator->member->fresh()->is_active)->toBeTrue()
        ->and($owner->member->fresh()->is_active)->toBeTrue()
        ->and($actor->member->fresh()->is_active)->toBeTrue();

    $toast = session('inertia.flash_data.toast') ?? session('toast');
    expect(json_encode($toast, JSON_UNESCAPED_UNICODE))
        ->toContain('Cap de Membres')->toContain('Admina')->toContain('Propietaria')->not->toContain('Anna');
});

test('an administrator deactivating with members skips the owner and themselves only', function () {
    $household = Household::factory()->create();
    $holder = choiceMembershipHolder($household);
    $owner = User::factory()->owner()->member($household)->create();
    $actor = User::factory()->administrator()->member($household)->create();

    $this->actingAs($actor)->withSession(['active_profile' => 'administrator'])
        ->post(route('households.deactivate', $household), ['with_members' => true])
        ->assertRedirect(route('households.index'));

    expect($holder->fresh()->is_active)->toBeFalse()
        ->and($owner->member->fresh()->is_active)->toBeTrue()
        ->and($actor->member->fresh()->is_active)->toBeTrue();
});

test('reactivating a household alone leaves its members inactive', function () {
    $household = Household::factory()->inactive()->create();
    $member = choiceMember($household, false);

    $this->actingAs(choiceActor())
        ->post(route('households.reactivate', $household), ['with_members' => false])
        ->assertRedirect(route('households.index'));

    expect($household->fresh()->is_active)->toBeTrue()
        ->and($member->fresh()->is_active)->toBeFalse();
});

test('reactivating with members reactivates every inactive member and skips and names the protected ones', function () {
    $household = Household::factory()->inactive()->create();
    $plain = choiceMember($household, false, 'Anna');
    $holder = choiceMembershipHolder($household);
    $holder->update(['is_active' => false]);
    $administrator = User::factory()->administrator()->member($household)->create(['name' => 'Admina']);
    $administrator->member->update(['is_active' => false]);
    $other = choiceMember(Household::factory()->inactive()->create(), false);

    $this->actingAs(choiceActor())
        ->post(route('households.reactivate', $household), ['with_members' => true])
        ->assertRedirect(route('households.index'));

    expect($household->fresh()->is_active)->toBeTrue()
        ->and($plain->fresh()->is_active)->toBeTrue()
        ->and($holder->fresh()->is_active)->toBeFalse()
        ->and($administrator->member->fresh()->is_active)->toBeFalse()
        ->and($other->fresh()->is_active)->toBeFalse();

    $toast = session('inertia.flash_data.toast') ?? session('toast');
    expect(json_encode($toast, JSON_UNESCAPED_UNICODE))->toContain('Cap de Membres')->toContain('Admina');
});

test('an administrator reactivates everybody', function () {
    $household = Household::factory()->inactive()->create();
    $holder = choiceMembershipHolder($household);
    $holder->update(['is_active' => false]);

    $this->actingAs(User::factory()->administrator()->create())
        ->post(route('households.reactivate', $household), ['with_members' => true])->assertRedirect();

    expect($holder->fresh()->is_active)->toBeTrue();
});

test('a member moved to another household first is never deactivated with the old one', function () {
    $old = Household::factory()->create();
    $target = Household::factory()->create();
    $moved = choiceMember($old, true, 'Moguda');
    $stays = choiceMember($old, true, 'Es queda');
    $actor = choiceActor();

    $this->actingAs($actor)->patch(route('members.household.update', $moved), ['household_id' => $target->id])->assertSessionHasNoErrors();
    $this->actingAs($actor)->post(route('households.deactivate', $old), ['with_members' => true])->assertRedirect();

    expect($moved->fresh()->is_active)->toBeTrue()
        ->and($moved->fresh()->household_id)->toBe($target->id)
        ->and($stays->fresh()->is_active)->toBeFalse();
});

test('a member moved after the deactivation is not changed by it again', function () {
    $old = Household::factory()->create();
    $target = Household::factory()->create();
    $moved = choiceMember($old, true, 'Moguda');
    $actor = choiceActor();

    $this->actingAs($actor)->post(route('households.deactivate', $old), ['with_members' => true])->assertRedirect();
    $this->actingAs($actor)->patch(route('members.household.update', $moved), ['household_id' => $target->id])->assertSessionHasNoErrors();

    expect($moved->fresh()->household_id)->toBe($target->id)
        ->and($target->fresh()->is_active)->toBeTrue()
        ->and($moved->fresh()->is_active)->toBeFalse();
});

test('a member moved away is not reactivated with the old household, in either order', function () {
    $old = Household::factory()->inactive()->create();
    $target = Household::factory()->create();
    $moved = choiceMember($old, false, 'Moguda');
    $actor = choiceActor();

    $this->actingAs($actor)->patch(route('members.household.update', $moved), ['household_id' => $target->id])->assertSessionHasNoErrors();
    $this->actingAs($actor)->post(route('households.reactivate', $old), ['with_members' => true])->assertRedirect();

    expect($moved->fresh()->is_active)->toBeFalse();

    $second = choiceMember($old->fresh(), false, 'Segona');
    $this->actingAs($actor)->post(route('households.deactivate', $old), ['with_members' => false])->assertRedirect();
    $this->actingAs($actor)->post(route('households.reactivate', $old), ['with_members' => true])->assertRedirect();
    $this->actingAs($actor)->patch(route('members.household.update', $second), ['household_id' => $target->id])->assertSessionHasNoErrors();

    expect($second->fresh()->is_active)->toBeTrue()
        ->and($second->fresh()->household_id)->toBe($target->id);
});

test('deactivating an already inactive household changes nothing', function () {
    $household = Household::factory()->inactive()->create();
    $member = choiceMember($household, true);

    $this->actingAs(choiceActor())
        ->post(route('households.deactivate', $household), ['with_members' => true])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->is_active)->toBeTrue();
});

test('the household list tells who each action would affect and who would be left out, by name', function () {
    $active = Household::factory()->create();
    $anna = choiceMember($active, true, 'Anna');
    $holder = choiceMembershipHolder($active);
    choiceMember($active, false, 'Inactiva');
    $inactive = Household::factory()->inactive()->create();
    $berta = choiceMember($inactive, false, 'Berta');

    $this->actingAs(choiceActor())->get(route('households.index'))->assertInertia(fn (Assert $page) => $page
        ->where('households', function ($households) use ($active, $inactive, $anna, $holder, $berta) {
            $activeRow = collect($households)->firstWhere('id', $active->id);
            $inactiveRow = collect($households)->firstWhere('id', $inactive->id);

            return $activeRow['affected_members'] === [
                ['id' => $anna->id, 'name' => 'Anna', 'left_out' => false],
                ['id' => $holder->id, 'name' => 'Cap de Membres', 'left_out' => true],
            ] && $inactiveRow['affected_members'] === [['id' => $berta->id, 'name' => 'Berta', 'left_out' => false]];
        }));
});

function choiceToast(): string
{
    return (string) json_encode(session('inertia.flash_data.toast') ?? session('toast'), JSON_UNESCAPED_UNICODE);
}

test('a request without a choice is rejected when an active member joined after the list showed nobody affected', function () {
    $household = Household::factory()->create();
    $actor = choiceActor();

    $this->actingAs($actor)->get(route('households.index'))->assertInertia(fn (Assert $page) => $page
        ->where('households', fn ($households) => collect($households)->firstWhere('id', $household->id)['affected_members'] === []));

    choiceMember($household, true, 'Nova');

    $this->actingAs($actor)->post(route('households.deactivate', $household))->assertSessionHasErrors('with_members');

    expect($household->fresh()->is_active)->toBeTrue();
});

test('reactivating an already active household is a no-op and says so', function () {
    $household = Household::factory()->create();
    $member = choiceMember($household, false);

    $this->actingAs(choiceActor())
        ->post(route('households.reactivate', $household), ['with_members' => true])
        ->assertRedirect(route('households.index'));

    expect($member->fresh()->is_active)->toBeFalse()
        ->and(choiceToast())->toContain('La unitat familiar ja estava activa.')->not->toContain('reactivats');
});

test('deactivating an already inactive household with members says it was already inactive', function () {
    $household = Household::factory()->inactive()->create();
    choiceMember($household, true);

    $this->actingAs(choiceActor())
        ->post(route('households.deactivate', $household), ['with_members' => true])
        ->assertRedirect(route('households.index'));

    expect(choiceToast())->toContain('La unitat familiar ja estava inactiva.');
});

test('the messages say whether the members followed', function () {
    $actor = choiceActor();
    $household = Household::factory()->create();
    choiceMember($household);

    $this->actingAs($actor)->post(route('households.deactivate', $household), ['with_members' => true]);
    expect(choiceToast())->toContain('Unitat familiar i membres desactivats.');

    $this->actingAs($actor)->post(route('households.reactivate', $household), ['with_members' => true]);
    expect(choiceToast())->toContain('Unitat familiar i membres reactivats.');

    $this->actingAs($actor)->post(route('households.deactivate', $household), ['with_members' => false]);
    expect(choiceToast())->toContain('Unitat familiar desactivada.')->not->toContain('membres');
});

test('the message names the acting person when they skip themselves', function () {
    $household = Household::factory()->create();
    $actor = choiceActor();
    $actor->member->update(['household_id' => $household->id]);

    $this->actingAs($actor)->post(route('households.deactivate', $household), ['with_members' => true]);

    expect(choiceToast())->toContain($actor->name);
});
