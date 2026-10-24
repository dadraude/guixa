<?php

namespace Tests\Support\InMemory;

use Guixa\Catalog\Domain\ProducerAccess;

final class FakeProducerAccess implements ProducerAccess
{
    /** @var array<string, true> */
    private array $suspended = [];

    public function suspend(string $producerId): void
    {
        $this->suspended[$producerId] = true;
    }

    public function restore(string $producerId): void
    {
        unset($this->suspended[$producerId]);
    }

    public function isSuspended(string $producerId): bool
    {
        return isset($this->suspended[$producerId]);
    }
}
