<?php

use Guixa\Membership\Domain\Error\EmailAlreadyInUse;
use Guixa\Membership\Domain\Error\ProducerAlreadyHasAccount;
use Guixa\Membership\Domain\Error\UnknownProducer;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\Membership\MembershipWorld;

const BAKER = '00000000-0000-7000-8000-0000000000b1';

beforeEach(function () {
    $this->world = new MembershipWorld;
    $this->world->producerDirectory->know(BAKER);
});

// S57 — Producer account (R36)

it('creates a producer account for the producer and emails the invitation (R36)', function () {
    $accountId = ($this->world->inviteProducerAccount)($this->world->manager, BAKER, 'Baker', 'baker@example.org');

    $account = $this->world->accounts->get($accountId);

    expect($account->roles)->toBe([Role::Producer])
        ->and($account->producerId)->toBe(BAKER)
        ->and($account->householdId)->toBeNull()
        ->and($account->hasAcceptedInvitation())->toBeFalse()
        ->and($this->world->sender->sent)->toHaveCount(1)
        ->and($this->world->sender->sent[0]['email'])->toBe('baker@example.org');
});

it('lets the baker open the invitation, set a password and log in (S57)', function () {
    $accountId = ($this->world->inviteProducerAccount)($this->world->manager, BAKER, 'Baker', 'baker@example.org');
    expect(($this->world->canLogIn)($accountId))->toBeFalse();

    ($this->world->acceptInvitation)($this->world->sender->lastToken(), 'a-new-password');

    expect(($this->world->canLogIn)($accountId))->toBeTrue()
        ->and($this->world->accounts->get($accountId)->producerId)->toBe(BAKER);
});

it('rejects a second account for the same producer and sends nothing (S57)', function () {
    ($this->world->inviteProducerAccount)($this->world->manager, BAKER, 'Baker', 'baker@example.org');

    expect(fn () => ($this->world->inviteProducerAccount)($this->world->manager, BAKER, 'Baker again', 'other@example.org'))
        ->toThrow(ProducerAlreadyHasAccount::class)
        ->and($this->world->sender->sent)->toHaveCount(1);
});

it('rejects an email that already belongs to an account (R34)', function () {
    ($this->world->inviteProducerAccount)($this->world->manager, BAKER, 'Baker', 'baker@example.org');
    $cheesemaker = '00000000-0000-7000-8000-0000000000b2';
    $this->world->producerDirectory->know($cheesemaker);

    expect(fn () => ($this->world->inviteProducerAccount)($this->world->manager, $cheesemaker, 'Cheese', 'Baker@Example.org'))
        ->toThrow(EmailAlreadyInUse::class);
});

it('rejects a producer that does not exist', function () {
    expect(fn () => ($this->world->inviteProducerAccount)(
        $this->world->manager,
        '00000000-0000-7000-8000-0000000000ff',
        'Ghost',
        'ghost@example.org',
    ))->toThrow(UnknownProducer::class)
        ->and($this->world->sender->sent)->toBe([]);
});

it('keeps producer accounts to managers (R36)', function (Role $role) {
    expect(fn () => ($this->world->inviteProducerAccount)(new Actor('a', [$role]), BAKER, 'Baker', 'baker@example.org'))
        ->toThrow(AuthorizationException::class)
        ->and($this->world->sender->sent)->toBe([]);
})->with([Role::Member, Role::Producer]);

// Producers admin page: who already has an account

it('lists each producer account with its email and whether the invitation is pending', function () {
    ($this->world->inviteProducerAccount)($this->world->manager, BAKER, 'Baker', 'baker@example.org');

    $views = ($this->world->listProducerAccounts)($this->world->manager);

    expect(array_keys($views))->toBe([BAKER])
        ->and($views[BAKER]->email)->toBe('baker@example.org')
        ->and($views[BAKER]->invitationPending)->toBeTrue();

    ($this->world->acceptInvitation)($this->world->sender->lastToken(), 'a-new-password');

    expect(($this->world->listProducerAccounts)($this->world->manager)[BAKER]->invitationPending)->toBeFalse();
});

it('ignores member accounts and keeps the list to managers', function () {
    ($this->world->addMember)($this->world->manager, ($this->world->createHousehold)($this->world->manager, 'H'), 'Anna', 'anna@example.org');

    expect(($this->world->listProducerAccounts)($this->world->manager))->toBe([])
        ->and(fn () => ($this->world->listProducerAccounts)(new Actor('a', [Role::Producer])))->toThrow(AuthorizationException::class);
});

// R3a, S50 — suspending and restoring the producer role

function activeProducerAccount(object $test): string
{
    $accountId = ($test->world->inviteProducerAccount)($test->world->manager, BAKER, 'Baker', 'baker@example.org');
    ($test->world->acceptInvitation)($test->world->sender->lastToken(), 'a-new-password');

    return $accountId;
}

it('removes the producer role, so an account with no other role cannot log in, and restores it (R3a, S50)', function () {
    $accountId = activeProducerAccount($this);

    ($this->world->suspendProducerRole)(BAKER);

    expect($this->world->accounts->get($accountId)->roles)->toBe([])
        ->and(($this->world->canLogIn)($accountId))->toBeFalse();

    ($this->world->restoreProducerRole)(BAKER);

    expect($this->world->accounts->get($accountId)->roles)->toBe([Role::Producer])
        ->and(($this->world->canLogIn)($accountId))->toBeTrue();
});

it('keeps the other roles of the account and lets it log in (R3a)', function () {
    $accountId = activeProducerAccount($this);
    ($this->world->grantStaffRole)($this->world->manager, $accountId, 'manager');

    ($this->world->suspendProducerRole)(BAKER);

    expect($this->world->accounts->get($accountId)->roles)->toBe([Role::Manager])
        ->and(($this->world->canLogIn)($accountId))->toBeTrue();

    ($this->world->restoreProducerRole)(BAKER);

    expect($this->world->accounts->get($accountId)->roles)->toContain(Role::Manager, Role::Producer);
});

it('is idempotent and ignores a producer with no account (R3a)', function () {
    $accountId = activeProducerAccount($this);

    ($this->world->suspendProducerRole)(BAKER);
    ($this->world->suspendProducerRole)(BAKER);
    expect($this->world->accounts->get($accountId)->roles)->toBe([]);

    ($this->world->restoreProducerRole)(BAKER);
    ($this->world->restoreProducerRole)(BAKER);
    expect($this->world->accounts->get($accountId)->roles)->toBe([Role::Producer]);

    ($this->world->suspendProducerRole)('00000000-0000-7000-8000-0000000000fe');
    ($this->world->restoreProducerRole)('00000000-0000-7000-8000-0000000000fe');
});
