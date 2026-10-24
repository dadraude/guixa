<?php

namespace Tests\Support\InMemory;

use Guixa\Catalog\Domain\ProducerCycles;

final class FakeProducerCycles implements ProducerCycles
{
    /** @var array<string, true> */
    private array $withUnsettledLines = [];

    /** The product has lines in a cycle that is not settled. */
    public function lock(string $productId): void
    {
        $this->withUnsettledLines[$productId] = true;
    }

    public function release(string $productId): void
    {
        unset($this->withUnsettledLines[$productId]);
    }

    /** @var list<string> producers stopped, in order */
    public array $stopped = [];

    public function stopParticipatingWhereNotSent(string $producerId): void
    {
        $this->stopped[] = $producerId;
    }

    public function hasUnsettledLines(string $productId): bool
    {
        return isset($this->withUnsettledLines[$productId]);
    }
}
