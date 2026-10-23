<?php

namespace Tests\Support\InMemory;

use Guixa\Catalog\Domain\Producer;
use Guixa\Catalog\Domain\ProducerRepository;

/** @extends InMemoryRepository<Producer> */
final class InMemoryProducerRepository extends InMemoryRepository implements ProducerRepository
{
    public function add(Producer $producer): void
    {
        $this->save($producer);
    }

    public function get(string $id): ?Producer
    {
        return $this->find($id);
    }

    public function list(): array
    {
        $producers = $this->all();
        usort($producers, fn (Producer $a, Producer $b) => [mb_strtolower($a->name()), $a->id] <=> [mb_strtolower($b->name()), $b->id]);

        return $producers;
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
