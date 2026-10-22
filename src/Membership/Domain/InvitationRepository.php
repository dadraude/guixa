<?php

namespace Guixa\Membership\Domain;

interface InvitationRepository
{
    /** Replaces the account's previous invitation, if any. */
    public function save(Invitation $invitation): void;

    public function findByTokenHash(string $tokenHash): ?Invitation;

    public function forAccount(string $accountId): ?Invitation;

    public function deleteForAccount(string $accountId): void;
}
