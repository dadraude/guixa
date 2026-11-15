<?php

namespace Tests\Support\InMemory\Wallet;

use Guixa\Wallet\Domain\AccountNames;

final class FakeAccountNames implements AccountNames
{
    /** @var array<string, string> */
    private array $known = [];

    /** @var list<list<string>> */
    public array $lookups = [];

    public function know(string $accountId, string $name): void
    {
        $this->known[$accountId] = $name;
    }

    public function forAccounts(array $accountIds): array
    {
        $this->lookups[] = $accountIds;

        return array_intersect_key($this->known, array_flip($accountIds));
    }
}
