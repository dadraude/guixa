<?php

namespace Tests\Support\InMemory;

use Guixa\Ordering\Domain\ReceptionProducerNames;

final class FakeReceptionProducerNames implements ReceptionProducerNames
{
    /** @var array<string, string> */
    private array $known = [];

    public int $reads = 0;

    public function know(string $producerId, string $name): void
    {
        $this->known[$producerId] = $name;
    }

    public function of(array $producerIds): array
    {
        $this->reads++;

        return array_intersect_key($this->known, array_flip($producerIds));
    }
}
