<?php

use Guixa\Membership\Domain\Error\EmailAlreadyInUse;
use Guixa\Membership\Domain\Error\ManagerAlreadyExists;
use Guixa\Shared\Domain\Role;
use Tests\Support\Membership\MembershipWorld;

beforeEach(function () {
    $this->world = new MembershipWorld;
});

// R36a — the first manager is created outside the app, with the usual invitation (D17)

it('creates the first manager and emails the invitation', function () {
    $accountId = ($this->world->createFirstManager)('Marta', 'Marta@Example.org');

    $account = $this->world->accounts->get($accountId);

    expect($account->roles)->toBe([Role::Manager])
        ->and($account->householdId)->toBeNull()
        ->and($account->email->value)->toBe('marta@example.org')
        ->and($account->hasAcceptedInvitation())->toBeFalse()
        ->and($this->world->sender->sent)->toHaveCount(1)
        ->and($this->world->sender->sent[0]['email'])->toBe('marta@example.org');
});

it('lets the first manager accept the invitation and log in', function () {
    $accountId = ($this->world->createFirstManager)('Marta', 'marta@example.org');

    ($this->world->acceptInvitation)($this->world->sender->lastToken(), 'a-new-password');

    expect(($this->world->canLogIn)($accountId))->toBeTrue();
});

it('is rejected once any manager exists', function () {
    ($this->world->createFirstManager)('Marta', 'marta@example.org');

    expect(fn () => ($this->world->createFirstManager)('Biel', 'biel@example.org'))
        ->toThrow(ManagerAlreadyExists::class);
    expect($this->world->sender->sent)->toHaveCount(1);
});

it('rejects an email that already belongs to an account', function () {
    $householdId = ($this->world->createHousehold)($this->world->manager, 'Household H');
    ($this->world->addMember)($this->world->manager, $householdId, 'Anna', 'anna@example.org');

    expect(fn () => ($this->world->createFirstManager)('Anna', 'anna@example.org'))
        ->toThrow(EmailAlreadyInUse::class);
});
