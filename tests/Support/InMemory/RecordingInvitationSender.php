<?php

namespace Tests\Support\InMemory;

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\Invitation;
use Guixa\Membership\Domain\InvitationSender;

final class RecordingInvitationSender implements InvitationSender
{
    /** @var list<array{email: string, token: string}> */
    public array $sent = [];

    public function send(Account $account, string $token, Invitation $invitation): void
    {
        $this->sent[] = ['email' => $account->email->value, 'token' => $token];
    }

    public function lastToken(): string
    {
        return $this->sent[array_key_last($this->sent)]['token'];
    }
}
