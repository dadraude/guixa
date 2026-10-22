<?php

use Guixa\Membership\Domain\Error\EmailAlreadyInUse;
use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Membership\Domain\Error\InvalidEmail;
use Guixa\Membership\Domain\Error\InvalidHouseholdName;
use Guixa\Membership\Domain\Error\InvitationAlreadyAccepted;
use Guixa\Membership\Domain\Error\InvitationExpired;
use Guixa\Membership\Domain\Error\InvitationNotValid;
use Guixa\Membership\Domain\Household;
use Guixa\Shared\Domain\Error\NotAllowed;
use Tests\Support\Membership\MembershipWorld;

beforeEach(function () {
    $this->world = new MembershipWorld;
});

function householdWithAnna(MembershipWorld $world): array
{
    $householdId = ($world->createHousehold)($world->manager, 'Household H');
    $accountId = ($world->addMember)($world->manager, $householdId, 'Anna', 'anna@example.org');

    return [$householdId, $accountId];
}

// S25 — Member invitation (R33, R34)

it('creates a household and links the invited person as a member who cannot log in yet', function () {
    [$householdId, $accountId] = householdWithAnna($this->world);

    $household = $this->world->households->get($householdId);
    $account = $this->world->accounts->get($accountId);

    expect($household->name)->toBe('Household H')
        ->and($household->isActive())->toBeTrue()
        ->and($account->householdId)->toBe($householdId)
        ->and($account->roles)->toBe([])
        ->and($account->hasAcceptedInvitation())->toBeFalse();
});

it('emails the invitation to the new member', function () {
    householdWithAnna($this->world);

    expect($this->world->sender->sent)->toHaveCount(1)
        ->and($this->world->sender->sent[0]['email'])->toBe('anna@example.org');
});

it('lets Anna set a password with the link, which activates the account (S25)', function () {
    [, $accountId] = householdWithAnna($this->world);

    ($this->world->acceptInvitation)($this->world->sender->lastToken(), 'a-new-password');

    expect($this->world->accounts->get($accountId)->hasAcceptedInvitation())->toBeTrue()
        ->and($this->world->passwords->passwords[$accountId])->toBe('a-new-password')
        ->and($this->world->invitations->forAccount($accountId))->toBeNull();
});

it('rejects a link older than 7 days (S25)', function () {
    householdWithAnna($this->world);
    $token = $this->world->sender->lastToken();

    $this->world->clock->travelTo('2026-10-29 10:00:01');

    expect(fn () => ($this->world->acceptInvitation)($token, 'a-new-password'))->toThrow(InvitationExpired::class);
});

it('accepts a link still inside the 7 days (R34)', function () {
    [, $accountId] = householdWithAnna($this->world);
    $token = $this->world->sender->lastToken();

    $this->world->clock->travelTo('2026-10-28 23:59');
    ($this->world->acceptInvitation)($token, 'a-new-password');

    expect($this->world->accounts->get($accountId)->hasAcceptedInvitation())->toBeTrue();
});

it('rejects a link replaced by a resent one and accepts the new one (S25)', function () {
    [, $accountId] = householdWithAnna($this->world);
    $oldToken = $this->world->sender->lastToken();

    ($this->world->resendInvitation)($this->world->manager, $accountId);
    $newToken = $this->world->sender->lastToken();

    expect($newToken)->not->toBe($oldToken)
        ->and(fn () => ($this->world->acceptInvitation)($oldToken, 'a-new-password'))->toThrow(InvitationNotValid::class);

    ($this->world->acceptInvitation)($newToken, 'a-new-password');

    expect($this->world->accounts->get($accountId)->hasAcceptedInvitation())->toBeTrue();
});

it('rejects a token that was never issued', function () {
    expect(fn () => ($this->world->acceptInvitation)('nope', 'a-new-password'))->toThrow(InvitationNotValid::class);
});

it('rejects using the same link twice', function () {
    householdWithAnna($this->world);
    $token = $this->world->sender->lastToken();
    ($this->world->acceptInvitation)($token, 'a-new-password');

    expect(fn () => ($this->world->acceptInvitation)($token, 'another-password'))->toThrow(InvitationNotValid::class);
});

it('changes the invitation email, which replaces the link and sends it to the new address (R34)', function () {
    [, $accountId] = householdWithAnna($this->world);
    $oldToken = $this->world->sender->lastToken();

    ($this->world->changeInvitationEmail)($this->world->manager, $accountId, ' Anna.New@Example.org ');

    expect($this->world->accounts->get($accountId)->email->value)->toBe('anna.new@example.org')
        ->and($this->world->sender->sent[1]['email'])->toBe('anna.new@example.org')
        ->and(fn () => ($this->world->acceptInvitation)($oldToken, 'a-new-password'))->toThrow(InvitationNotValid::class);
});

it('rejects resending or changing the email once the invitation was accepted (R34)', function () {
    [, $accountId] = householdWithAnna($this->world);
    ($this->world->acceptInvitation)($this->world->sender->lastToken(), 'a-new-password');

    expect(fn () => ($this->world->resendInvitation)($this->world->manager, $accountId))
        ->toThrow(InvitationAlreadyAccepted::class)
        ->and(fn () => ($this->world->changeInvitationEmail)($this->world->manager, $accountId, 'other@example.org'))
        ->toThrow(InvitationAlreadyAccepted::class);
});

it('rejects an email that already belongs to an account', function () {
    [$householdId] = householdWithAnna($this->world);

    expect(fn () => ($this->world->addMember)($this->world->manager, $householdId, 'Other Anna', 'ANNA@example.org'))
        ->toThrow(EmailAlreadyInUse::class)
        ->and($this->world->sender->sent)->toHaveCount(1);
});

it('rejects an invalid email and an unknown household', function () {
    $householdId = ($this->world->createHousehold)($this->world->manager, 'Household H');

    expect(fn () => ($this->world->addMember)($this->world->manager, $householdId, 'Anna', 'not-an-email'))
        ->toThrow(InvalidEmail::class)
        ->and(fn () => ($this->world->addMember)($this->world->manager, '00000000-0000-7000-8000-000000000099', 'Anna', 'anna@example.org'))
        ->toThrow(HouseholdNotFound::class);
});

it('reactivates an inactive household when a member is added to it (R35a)', function () {
    $householdId = ($this->world->createHousehold)($this->world->manager, 'Household H');
    $this->world->households->save(Household::restore($householdId, 'Household H', new DateTimeImmutable('2026-10-01')));

    ($this->world->addMember)($this->world->manager, $householdId, 'Anna', 'anna@example.org');

    expect($this->world->households->get($householdId)->isActive())->toBeTrue();
});

// R33 — households

it('renames a household and rejects a blank name', function () {
    $householdId = ($this->world->createHousehold)($this->world->manager, 'Household H');

    ($this->world->renameHousehold)($this->world->manager, $householdId, '  Casa Roca ');

    expect($this->world->households->get($householdId)->name)->toBe('Casa Roca')
        ->and(fn () => ($this->world->renameHousehold)($this->world->manager, $householdId, '  '))
        ->toThrow(InvalidHouseholdName::class)
        ->and(fn () => ($this->world->createHousehold)($this->world->manager, ''))
        ->toThrow(InvalidHouseholdName::class);
});

it('lists households with their member count and shows a household with its pending invitations', function () {
    [$householdId] = householdWithAnna($this->world);
    ($this->world->createHousehold)($this->world->manager, 'Empty');

    $summaries = ($this->world->listHouseholds)($this->world->manager);
    $detail = ($this->world->getHousehold)($this->world->manager, $householdId);

    expect(array_map(fn ($s) => [$s->name, $s->memberCount, $s->active], $summaries))
        ->toBe([['Household H', 1, true], ['Empty', 0, true]])
        ->and($detail->members)->toHaveCount(1)
        ->and($detail->members[0]->email)->toBe('anna@example.org')
        ->and($detail->members[0]->invitationPending)->toBeTrue()
        ->and($detail->members[0]->invitationExpiresAt)->toBe('2026-10-29T09:00:00+01:00')
        ->and($detail->members[0]->invitationExpired)->toBeFalse();

    $this->world->clock->travelTo('2026-10-29 10:00:01');

    expect(($this->world->getHousehold)($this->world->manager, $householdId)->members[0]->invitationExpired)->toBeTrue();
});

// S56 — Only managers create households (R33)

it('rejects a member who tries to create a household or add a member (S56)', function () {
    [$householdId, $accountId] = householdWithAnna($this->world);
    ($this->world->acceptInvitation)($this->world->sender->lastToken(), 'a-new-password');

    expect(fn () => ($this->world->createHousehold)($this->world->member, 'Sneaky'))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->addMember)($this->world->member, $householdId, 'Biel', 'biel@example.org'))
        ->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->renameHousehold)($this->world->member, $householdId, 'Mine'))
        ->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->resendInvitation)($this->world->member, $accountId))
        ->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->changeInvitationEmail)($this->world->member, $accountId, 'x@example.org'))
        ->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->listHouseholds)($this->world->member))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->getHousehold)($this->world->member, $householdId))->toThrow(NotAllowed::class);
});

// S25 — before accepting, login is rejected

it('does not let an account log in before it accepts the invitation, and does after', function () {
    [, $accountId] = householdWithAnna($this->world);

    expect(($this->world->canLogIn)($accountId))->toBeFalse();

    ($this->world->acceptInvitation)($this->world->sender->lastToken(), 'a-new-password');

    expect(($this->world->canLogIn)($accountId))->toBeTrue()
        ->and(($this->world->canLogIn)('00000000-0000-7000-8000-000000000099'))->toBeFalse();
});

it('rejects changing the invitation email to one another account uses, sending nothing', function () {
    [$householdId, $accountId] = householdWithAnna($this->world);
    ($this->world->addMember)($this->world->manager, $householdId, 'Biel', 'biel@example.org');
    $sent = count($this->world->sender->sent);

    expect(fn () => ($this->world->changeInvitationEmail)($this->world->manager, $accountId, 'BIEL@Example.org'))
        ->toThrow(EmailAlreadyInUse::class)
        ->and($this->world->sender->sent)->toHaveCount($sent)
        ->and($this->world->accounts->get($accountId)->email->value)->toBe('anna@example.org');
});

it('resends the invitation when the email is changed to the current one', function () {
    [, $accountId] = householdWithAnna($this->world);

    ($this->world->changeInvitationEmail)($this->world->manager, $accountId, 'anna@example.org');

    expect($this->world->sender->sent)->toHaveCount(2)
        ->and($this->world->sender->sent[1]['email'])->toBe('anna@example.org');
});
