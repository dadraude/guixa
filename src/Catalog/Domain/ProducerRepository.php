<?php

namespace Guixa\Catalog\Domain;

interface ProducerRepository
{
    public function nextId(): string;

    /** Inserts the producer, or replaces the one with the same id. */
    public function add(Producer $producer): void;

    public function get(string $id): ?Producer;

    /** @return list<Producer> ordered by name, then id */
    public function list(): array;
}
