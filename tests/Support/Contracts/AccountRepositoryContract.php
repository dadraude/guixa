<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Domain\Role;
use PHPUnit\Framework\Attributes\Test;

/** Integration tests also need household and producer rows to point at: they implement `householdId()` and `producerId()`. */
trait AccountRepositoryContract
{
    abstract protected function repository(): AccountRepository;

    abstract protected function householdId(): string;

    /** An existing producer for producer accounts to point at. */
    abstract protected function producerId(): string;

    #[Test]
    public function it_finds_what_was_saved(): void
    {
        $repository = $this->repository();
        $account = Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId());

        $repository->save($account);

        $found = $repository->get($account->id);
        $this->assertSame('Anna', $found?->name);
        $this->assertSame('anna@example.org', $found->email->value);
        $this->assertSame($this->householdId(), $found->householdId);
        $this->assertSame([Role::Member], $found->roles);
        $this->assertFalse($found->hasAcceptedInvitation());
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        $repository = $this->repository();

        $this->assertNull($repository->get($repository->nextId()));
    }

    #[Test]
    public function it_keeps_the_changes_of_a_saved_account(): void
    {
        $repository = $this->repository();
        $account = Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId());
        $repository->save($account);

        $account->changeInvitationEmail(Email::from('anna.new@example.org'));
        $account->acceptInvitation(new DateTimeImmutable('2026-10-22 10:00:00+00:00'));
        $repository->save($account);

        $found = $repository->get($account->id);
        $this->assertSame('anna.new@example.org', $found?->email->value);
        $this->assertTrue($found->hasAcceptedInvitation());
    }

    #[Test]
    public function it_tells_whether_an_email_is_taken(): void
    {
        $repository = $this->repository();
        $repository->save(Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId()));

        $this->assertTrue($repository->emailIsTaken(Email::from('ANNA@example.org')));
        $this->assertFalse($repository->emailIsTaken(Email::from('biel@example.org')));
    }

    #[Test]
    public function it_lists_the_members_of_a_household(): void
    {
        $repository = $this->repository();
        $repository->save(Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId()));
        $repository->save(Account::inviteMember($repository->nextId(), 'Biel', Email::from('biel@example.org'), $this->householdId()));

        $this->assertCount(2, $repository->membersOf($this->householdId()));
        $this->assertSame([], $repository->membersOf($repository->nextId()));
    }

    #[Test]
    public function it_finds_a_producer_account_with_its_role_and_producer(): void
    {
        $repository = $this->repository();
        $account = Account::inviteProducer($repository->nextId(), 'Baker', Email::from('baker@example.org'), $this->producerId());

        $repository->save($account);

        $found = $repository->get($account->id);
        $this->assertSame([Role::Producer], $found?->roles);
        $this->assertSame($this->producerId(), $found->producerId);
        $this->assertNull($found->householdId);
    }

    #[Test]
    public function it_tells_whether_a_producer_has_an_account(): void
    {
        $repository = $this->repository();
        $this->assertFalse($repository->producerHasAccount($this->producerId()));

        $repository->save(Account::inviteProducer($repository->nextId(), 'Baker', Email::from('baker@example.org'), $this->producerId()));

        $this->assertTrue($repository->producerHasAccount($this->producerId()));
    }

    #[Test]
    public function it_lists_only_the_accounts_that_belong_to_a_producer(): void
    {
        $repository = $this->repository();
        $repository->save(Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId()));
        $baker = Account::inviteProducer($repository->nextId(), 'Baker', Email::from('baker@example.org'), $this->producerId());
        $repository->save($baker);

        $ids = array_map(fn (Account $account) => $account->id, $repository->producerAccounts());

        $this->assertSame([$baker->id], $ids);
    }

    #[Test]
    public function it_tells_whether_a_manager_exists(): void
    {
        $repository = $this->repository();
        $repository->save(Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId()));
        $this->assertFalse($repository->hasManager());

        $repository->save(Account::inviteManager($repository->nextId(), 'Marta', Email::from('marta@example.org')));
        $this->assertTrue($repository->hasManager());
    }
}
