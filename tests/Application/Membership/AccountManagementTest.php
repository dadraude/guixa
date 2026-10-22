<?php

use Guixa\Membership\Domain\Error\AccountDeactivated;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Membership\Domain\Error\LastActiveManager;
use Guixa\Membership\Domain\Error\LastManager;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\Membership\MembershipWorld;

beforeEach(function () {
    $this->world = new MembershipWorld;
});

/** Anna and Biel are active members of household H (S43). */
function householdWithAnnaAndBiel(MembershipWorld $world): array
{
    $householdId = ($world->createHousehold)($world->manager, 'Household H');
    $annaId = ($world->addMember)($world->manager, $householdId, 'Anna', 'anna@example.org');
    $bielId = ($world->addMember)($world->manager, $householdId, 'Biel', 'biel@example.org');

    return [$householdId, $annaId, $bielId];
}

// S43 — Moving a member (R35, R35a)

it('links the same account to the new household and leaves the old one with the others (S43)', function () {
    [$hId, $annaId, $bielId] = householdWithAnnaAndBiel($this->world);
    $jId = ($this->world->createHousehold)($this->world->manager, 'Household J');

    ($this->world->moveMember)($this->world->manager, $annaId, $jId);

    expect($this->world->accounts->get($annaId)->householdId)->toBe($jId)
        ->and($this->world->accounts->get($bielId)->householdId)->toBe($hId)
        ->and($this->world->households->get($hId)->isActive())->toBeTrue();
});

it('deactivates the household when its last member is moved away (S43, R35a)', function () {
    [$hId, $annaId, $bielId] = householdWithAnnaAndBiel($this->world);
    $jId = ($this->world->createHousehold)($this->world->manager, 'Household J');

    ($this->world->moveMember)($this->world->manager, $annaId, $jId);
    ($this->world->unlinkMember)($this->world->manager, $bielId);

    expect($this->world->accounts->get($bielId)->householdId)->toBeNull()
        ->and($this->world->households->get($hId)->isActive())->toBeFalse();
});

it('reactivates an inactive household that a member is moved to (R35a)', function () {
    [$hId, $annaId, $bielId] = householdWithAnnaAndBiel($this->world);
    $jId = ($this->world->createHousehold)($this->world->manager, 'Household J');
    ($this->world->unlinkMember)($this->world->manager, $annaId);
    ($this->world->unlinkMember)($this->world->manager, $bielId);
    expect($this->world->households->get($hId)->isActive())->toBeFalse();

    ($this->world->moveMember)($this->world->manager, $annaId, $hId);

    expect($this->world->households->get($hId)->isActive())->toBeTrue()
        ->and($this->world->accounts->get($annaId)->householdId)->toBe($hId)
        ->and($this->world->households->get($jId)->isActive())->toBeTrue();
});

it('keeps the account active when it is unlinked and lets it be linked to the same household again (R35)', function () {
    [$hId, $annaId] = householdWithAnnaAndBiel($this->world);
    ($this->world->acceptInvitation)($this->world->sender->tokenFor('anna@example.org'), 'a-new-password');

    ($this->world->unlinkMember)($this->world->manager, $annaId);

    expect($this->world->accounts->get($annaId)->householdId)->toBeNull()
        ->and($this->world->accounts->get($annaId)->deactivatedAt)->toBeNull();

    ($this->world->moveMember)($this->world->manager, $annaId, $hId);

    expect($this->world->accounts->get($annaId)->householdId)->toBe($hId);
});

it('does nothing when the member is moved to the household it already belongs to', function () {
    [$hId, $annaId, $bielId] = householdWithAnnaAndBiel($this->world);

    ($this->world->moveMember)($this->world->manager, $annaId, $hId);

    expect($this->world->accounts->get($annaId)->householdId)->toBe($hId)
        ->and($this->world->households->get($hId)->isActive())->toBeTrue();
});

it('rejects moving to an unknown household or moving an unknown or deactivated account', function () {
    [, $annaId] = householdWithAnnaAndBiel($this->world);
    $jId = ($this->world->createHousehold)($this->world->manager, 'Household J');
    $unknown = $this->world->accounts->nextId();

    expect(fn () => ($this->world->moveMember)($this->world->manager, $annaId, $unknown))->toThrow(HouseholdNotFound::class)
        ->and(fn () => ($this->world->moveMember)($this->world->manager, $unknown, $jId))->toThrow(AccountNotFound::class)
        ->and(fn () => ($this->world->unlinkMember)($this->world->manager, $unknown))->toThrow(AccountNotFound::class);

    ($this->world->deactivateAccount)($this->world->manager, $annaId);

    expect(fn () => ($this->world->moveMember)($this->world->manager, $annaId, $jId))->toThrow(AccountDeactivated::class);
});

it('lets only a manager move or unlink members (R33)', function () {
    [$hId, $annaId] = householdWithAnnaAndBiel($this->world);

    expect(fn () => ($this->world->moveMember)($this->world->member, $annaId, $hId))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->unlinkMember)($this->world->member, $annaId))->toThrow(NotAllowed::class);
});

// S44 — Several roles (R36a)

it('lets a member who is granted the manager role keep both roles (S44)', function () {
    [, $annaId] = householdWithAnnaAndBiel($this->world);

    ($this->world->grantManagerRole)($this->world->manager, $annaId);

    $account = $this->world->accounts->get($annaId);
    expect($account->roles)->toBe([Role::Manager])
        ->and($account->householdId)->not->toBeNull();
});

it('granting the manager role twice leaves one manager role', function () {
    [, $annaId] = householdWithAnnaAndBiel($this->world);

    ($this->world->grantManagerRole)($this->world->manager, $annaId);
    ($this->world->grantManagerRole)($this->world->manager, $annaId);

    expect($this->world->accounts->get($annaId)->roles)->toBe([Role::Manager]);
});

it('removes the manager role when another active manager remains (R36a)', function () {
    [, $annaId] = householdWithAnnaAndBiel($this->world);
    $carlaId = $this->world->addManager('Carla', 'carla@example.org');
    ($this->world->grantManagerRole)($this->world->manager, $annaId);

    ($this->world->removeManagerRole)($this->world->manager, $annaId);

    expect($this->world->accounts->get($annaId)->roles)->toBe([])
        ->and($this->world->accounts->get($annaId)->householdId)->not->toBeNull()
        ->and($this->world->accounts->get($carlaId)->roles)->toBe([Role::Manager]);
});

it('rejects removing the manager role from the last manager (S44)', function () {
    $carlaId = $this->world->addManager('Carla', 'carla@example.org');

    expect(fn () => ($this->world->removeManagerRole)($this->world->manager, $carlaId))->toThrow(LastManager::class)
        ->and($this->world->accounts->get($carlaId)->roles)->toBe([Role::Manager]);
});

it('does not count a deactivated manager as a remaining manager (R36a)', function () {
    $carlaId = $this->world->addManager('Carla', 'carla@example.org');
    $danId = $this->world->addManager('Dan', 'dan@example.org');
    ($this->world->deactivateAccount)($this->world->manager, $danId);

    expect(fn () => ($this->world->removeManagerRole)($this->world->manager, $carlaId))->toThrow(LastManager::class);
});

it('lets the manager role be removed from a deactivated manager', function () {
    $this->world->addManager('Carla', 'carla@example.org');
    $danId = $this->world->addManager('Dan', 'dan@example.org');
    ($this->world->deactivateAccount)($this->world->manager, $danId);

    ($this->world->removeManagerRole)($this->world->manager, $danId);

    expect($this->world->accounts->get($danId)->roles)->toBe([]);
});

it('lets only a manager grant or remove the manager role (R36a)', function () {
    [, $annaId] = householdWithAnnaAndBiel($this->world);

    expect(fn () => ($this->world->grantManagerRole)($this->world->member, $annaId))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->removeManagerRole)($this->world->member, $annaId))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->grantManagerRole)($this->world->manager, $this->world->accounts->nextId()))->toThrow(AccountNotFound::class);
});

// S59 — Deactivating an account (R36b)

it('rejects deactivating the only active manager (S59)', function () {
    $carlaId = $this->world->addManager('Carla', 'carla@example.org');

    expect(fn () => ($this->world->deactivateAccount)($this->world->manager, $carlaId))->toThrow(LastActiveManager::class)
        ->and($this->world->accounts->get($carlaId)->deactivatedAt)->toBeNull();
});

it('deactivates an account: it is unlinked, cannot log in and keeps its history (S59)', function () {
    [$hId, $annaId, $bielId] = householdWithAnnaAndBiel($this->world);
    $this->world->addManager('Carla', 'carla@example.org');
    ($this->world->acceptInvitation)($this->world->sender->tokenFor('anna@example.org'), 'a-new-password');
    expect(($this->world->canLogIn)($annaId))->toBeTrue();

    ($this->world->deactivateAccount)($this->world->manager, $annaId);

    $anna = $this->world->accounts->get($annaId);
    expect($anna->deactivatedAt)->not->toBeNull()
        ->and($anna->householdId)->toBeNull()
        ->and($anna->name)->toBe('Anna')
        ->and($anna->roles)->toBe([])
        ->and(($this->world->canLogIn)($annaId))->toBeFalse()
        ->and($this->world->households->get($hId)->isActive())->toBeTrue()
        ->and($this->world->accounts->get($bielId)->householdId)->toBe($hId);
});

it('deactivates the household when its last member account is deactivated (R35a)', function () {
    [$hId, $annaId, $bielId] = householdWithAnnaAndBiel($this->world);

    ($this->world->deactivateAccount)($this->world->manager, $annaId);
    ($this->world->deactivateAccount)($this->world->manager, $bielId);

    expect($this->world->households->get($hId)->isActive())->toBeFalse();
});

it('deactivates a manager when another active manager remains', function () {
    $this->world->addManager('Carla', 'carla@example.org');
    $danId = $this->world->addManager('Dan', 'dan@example.org');

    ($this->world->deactivateAccount)($this->world->manager, $danId);

    expect($this->world->accounts->get($danId)->deactivatedAt)->not->toBeNull();
});

it('deactivating an account twice keeps the first deactivation time', function () {
    [, $annaId] = householdWithAnnaAndBiel($this->world);
    ($this->world->deactivateAccount)($this->world->manager, $annaId);
    $first = $this->world->accounts->get($annaId)->deactivatedAt;

    $this->world->clock->travelTo('2026-10-23 10:00');
    ($this->world->deactivateAccount)($this->world->manager, $annaId);

    expect($this->world->accounts->get($annaId)->deactivatedAt)->toEqual($first);
});

it('reactivates an account: it stays without a household, so it logs in once it is linked again (R36b, R35)', function () {
    [$hId, $annaId] = householdWithAnnaAndBiel($this->world);
    ($this->world->acceptInvitation)($this->world->sender->tokenFor('anna@example.org'), 'a-new-password');
    ($this->world->deactivateAccount)($this->world->manager, $annaId);

    ($this->world->reactivateAccount)($this->world->manager, $annaId);

    $anna = $this->world->accounts->get($annaId);
    expect($anna->deactivatedAt)->toBeNull()
        ->and($anna->householdId)->toBeNull()
        ->and(($this->world->canLogIn)($annaId))->toBeFalse();

    ($this->world->moveMember)($this->world->manager, $annaId, $hId);

    expect(($this->world->canLogIn)($annaId))->toBeTrue();
});

// Membership is the household link (R35)

it('lets a manager-only account linked to a household order for it', function () {
    $hId = ($this->world->createHousehold)($this->world->manager, 'Household H');
    $carlaId = $this->world->addManager('Carla', 'carla@example.org');

    ($this->world->moveMember)($this->world->manager, $carlaId, $hId);

    $carla = $this->world->accounts->get($carlaId);
    $actor = new Actor($carla->id, $carla->roles, $carla->householdId);
    $actor->assertMemberOf($hId);
    expect($carla->roles)->toBe([Role::Manager])
        ->and($actor->hasRole(Role::Member))->toBeTrue();
});

it('stops a manager-only account from being a member of the household it was unlinked from', function () {
    $hId = ($this->world->createHousehold)($this->world->manager, 'Household H');
    $carlaId = $this->world->addManager('Carla', 'carla@example.org');
    ($this->world->moveMember)($this->world->manager, $carlaId, $hId);

    ($this->world->unlinkMember)($this->world->manager, $carlaId);

    $carla = $this->world->accounts->get($carlaId);
    $actor = new Actor($carla->id, $carla->roles, $carla->householdId);
    expect(fn () => $actor->assertMemberOf($hId))->toThrow(NotAllowed::class)
        ->and(($this->world->canLogIn)($carlaId))->toBeTrue(); // still a manager
});

it('keeps an unlinked account with no other role from logging in until it is linked again', function () {
    [$hId, $annaId] = householdWithAnnaAndBiel($this->world);
    ($this->world->acceptInvitation)($this->world->sender->tokenFor('anna@example.org'), 'a-new-password');
    expect(($this->world->canLogIn)($annaId))->toBeTrue();

    ($this->world->unlinkMember)($this->world->manager, $annaId);

    expect(($this->world->canLogIn)($annaId))->toBeFalse();

    ($this->world->moveMember)($this->world->manager, $annaId, $hId);

    expect(($this->world->canLogIn)($annaId))->toBeTrue();
});

it('keeps the invited member stored without any role', function () {
    [, $annaId] = householdWithAnnaAndBiel($this->world);

    expect($this->world->accounts->get($annaId)->roles)->toBe([]);
});

it('lets only a manager deactivate or reactivate accounts, and rejects unknown ones (R36b)', function () {
    [, $annaId] = householdWithAnnaAndBiel($this->world);
    $unknown = $this->world->accounts->nextId();

    expect(fn () => ($this->world->deactivateAccount)($this->world->member, $annaId))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->reactivateAccount)($this->world->member, $annaId))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->deactivateAccount)($this->world->manager, $unknown))->toThrow(AccountNotFound::class)
        ->and(fn () => ($this->world->reactivateAccount)($this->world->manager, $unknown))->toThrow(AccountNotFound::class);
});

// Accounts admin page

it('lists every account with its roles, household and state', function () {
    [$hId, $annaId] = householdWithAnnaAndBiel($this->world);
    $carlaId = $this->world->addManager('Carla', 'carla@example.org');
    ($this->world->deactivateAccount)($this->world->manager, $annaId);

    $accounts = collect(($this->world->listAccounts)($this->world->manager))->keyBy('accountId');

    expect($accounts)->toHaveCount(3)
        ->and($accounts[$annaId]->active)->toBeFalse()
        ->and($accounts[$annaId]->householdName)->toBeNull()
        ->and($accounts[$carlaId]->roles)->toBe(['manager'])
        ->and($accounts[$carlaId]->active)->toBeTrue()
        ->and(collect($accounts)->firstWhere('name', 'Biel')->householdName)->toBe('Household H');
    expect(fn () => ($this->world->listAccounts)($this->world->member))->toThrow(NotAllowed::class);
});
