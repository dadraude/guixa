<?php

namespace Tests\Support\InMemory;

use Guixa\Membership\Domain\Invitation;
use Guixa\Membership\Domain\InvitationRepository;

/** @extends InMemoryRepository<Invitation> */
final class InMemoryInvitationRepository extends InMemoryRepository implements InvitationRepository
{
    /** @param Invitation $item */
    public function save(object $item): void
    {
        parent::save($item);
    }

    public function findByTokenHash(string $tokenHash): ?Invitation
    {
        foreach ($this->all() as $invitation) {
            if ($invitation->tokenHash === $tokenHash) {
                return $invitation;
            }
        }

        return null;
    }

    public function forAccount(string $accountId): ?Invitation
    {
        return $this->find($accountId);
    }

    public function deleteForAccount(string $accountId): void
    {
        $this->remove($accountId);
    }

    protected function idOf(object $item): string
    {
        return $item->accountId;
    }
}
