<?php

namespace Guixa\Stock\Domain;

interface ReturnItemRepository
{
    public function nextId(): string;

    /** Inserts the item, or replaces the one with the same id. */
    public function add(ReturnItem $item): void;

    public function get(string $id): ?ReturnItem;

    /** @return list<ReturnItem> ordered by name, then id */
    public function list(): array;
}
