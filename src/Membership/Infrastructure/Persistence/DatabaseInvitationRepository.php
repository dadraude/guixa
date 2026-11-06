<?php

namespace Guixa\Membership\Infrastructure\Persistence;

use DateTimeImmutable;
use Guixa\Membership\Domain\Invitation;
use Guixa\Membership\Domain\InvitationRepository;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DatabaseInvitationRepository implements InvitationRepository
{
    public function save(Invitation $invitation): void
    {
        DB::table('invitations')->upsert(
            [[
                'account_id' => $invitation->accountId,
                'token_hash' => $invitation->tokenHash,
                'sent_at' => $invitation->sentAt->format(DATE_ATOM),
            ]],
            ['account_id'],
            ['token_hash', 'sent_at'],
        );
    }

    public function findByTokenHash(string $tokenHash): ?Invitation
    {
        return $this->hydrate(DB::table('invitations')->where('token_hash', $tokenHash)->first());
    }

    public function forAccount(string $accountId): ?Invitation
    {
        return $this->hydrate(DB::table('invitations')->where('account_id', $accountId)->first());
    }

    public function deleteForAccount(string $accountId): void
    {
        DB::table('invitations')->where('account_id', $accountId)->delete();
    }

    private function hydrate(?stdClass $row): ?Invitation
    {
        return $row === null ? null : Invitation::restore($row->account_id, $row->token_hash, new DateTimeImmutable($row->sent_at));
    }
}
