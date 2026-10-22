<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use Guixa\Membership\Domain\Invitation;
use Guixa\Membership\Domain\InvitationRepository;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests also need an account row for the invitation to point at: they implement `accountId()`.
 */
trait InvitationRepositoryContract
{
    abstract protected function repository(): InvitationRepository;

    abstract protected function accountId(): string;

    #[Test]
    public function it_finds_an_invitation_by_account_and_by_token_hash(): void
    {
        $repository = $this->repository();
        $accountId = $this->accountId();
        $invitation = Invitation::issue($accountId, 'token-1', new DateTimeImmutable('2026-10-22 10:00:00+00:00'));

        $repository->save($invitation);

        $this->assertSame($invitation->tokenHash, $repository->forAccount($accountId)?->tokenHash);
        $this->assertSame($accountId, $repository->findByTokenHash(Invitation::hash('token-1'))?->accountId);
        $this->assertEquals($invitation->sentAt->getTimestamp(), $repository->forAccount($accountId)?->sentAt->getTimestamp());
    }

    #[Test]
    public function it_replaces_the_previous_invitation_of_the_account(): void
    {
        $repository = $this->repository();
        $accountId = $this->accountId();
        $now = new DateTimeImmutable('2026-10-22 10:00:00+00:00');
        $repository->save(Invitation::issue($accountId, 'old', $now));

        $repository->save(Invitation::issue($accountId, 'new', $now));

        $this->assertNull($repository->findByTokenHash(Invitation::hash('old')));
        $this->assertNotNull($repository->findByTokenHash(Invitation::hash('new')));
    }

    #[Test]
    public function it_deletes_the_invitation_of_an_account(): void
    {
        $repository = $this->repository();
        $accountId = $this->accountId();
        $repository->save(Invitation::issue($accountId, 'token', new DateTimeImmutable('2026-10-22 10:00:00+00:00')));

        $repository->deleteForAccount($accountId);

        $this->assertNull($repository->forAccount($accountId));
        $this->assertNull($repository->findByTokenHash(Invitation::hash('token')));
    }
}
