<?php

namespace Tests\Support\InMemory;

use Guixa\Stock\Domain\AccountNames;

final class FakeAccountNames implements AccountNames
{
    /** @var array<string, string> */
    private array $known = [];

    public function know(string $accountId, string $name): void
    {
        $this->known[$accountId] = $name;
    }

    public function forAccounts(array $accountIds): array
    {
        return array_intersect_key($this->known, array_flip($accountIds));
    }
}
