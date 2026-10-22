<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Domain\Role;
use PHPUnit\Framework\Attributes\Test;

/** Integration tests also need a household row for members to point at: they implement `householdId()`. */
trait AccountRepositoryContract
{
    abstract protected function repository(): AccountRepository;

    abstract protected function householdId(): string;

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
}
