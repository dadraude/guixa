<?php

namespace Tests\Support\InMemory;

use Guixa\Membership\Domain\AccountPasswords;

final class RecordingAccountPasswords implements AccountPasswords
{
    /** @var array<string, string> */
    public array $passwords = [];

    public function change(string $accountId, string $plainPassword): void
    {
        $this->passwords[$accountId] = $plainPassword;
    }
}
