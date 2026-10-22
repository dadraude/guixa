<?php

namespace Tests\Support\InMemory;

use Guixa\Stock\Domain\ReturnItem;
use Guixa\Stock\Domain\ReturnItemRepository;

/** @extends InMemoryRepository<ReturnItem> */
final class InMemoryReturnItemRepository extends InMemoryRepository implements ReturnItemRepository
{
    public function add(ReturnItem $item): void
    {
        $this->save($item);
    }

    public function get(string $id): ?ReturnItem
    {
        return $this->find($id);
    }

    public function list(): array
    {
        $items = $this->all();
        usort($items, fn (ReturnItem $a, ReturnItem $b) => [mb_strtolower($a->name()), $a->id] <=> [mb_strtolower($b->name()), $b->id]);

        return $items;
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
