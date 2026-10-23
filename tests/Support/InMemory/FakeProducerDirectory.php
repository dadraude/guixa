<?php

namespace Tests\Support\InMemory;

use Guixa\Membership\Domain\ProducerDirectory;

final class FakeProducerDirectory implements ProducerDirectory
{
    /** @var array<string, true> */
    private array $known = [];

    public function know(string $producerId): void
    {
        $this->known[$producerId] = true;
    }

    public function exists(string $producerId): bool
    {
        return isset($this->known[$producerId]);
    }
}
