<?php

namespace Tests\Unit\Membership;

use DateTimeImmutable;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Invitation;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\AccountRepositoryContract;
use Tests\Support\InMemory\InMemoryAccountRepository;
use Tests\Support\InMemory\InMemoryInvitationRepository;

final class InMemoryAccountRepositoryTest extends TestCase
{
    use AccountRepositoryContract;

    private ?InMemoryAccountRepository $repository = null;

    private ?InMemoryInvitationRepository $invitations = null;

    protected function repository(): AccountRepository
    {
        $this->invitations ??= new InMemoryInvitationRepository;

        return $this->repository ??= new InMemoryAccountRepository($this->invitations);
    }

    protected function inviteAt(Account $account, DateTimeImmutable $sentAt): void
    {
        $this->repository();
        $this->invitations->save(Invitation::issue($account->id, 'token-'.$account->id, $sentAt));
    }

    protected function householdId(): string
    {
        return '00000000-0000-7000-8000-00000000000c';
    }

    protected function producerId(): string
    {
        return '00000000-0000-7000-8000-0000000000b1';
    }
}
