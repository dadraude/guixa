<?php

namespace Guixa\Catalog\Domain;

interface ProductRepository
{
    public function nextId(): string;

    /** Inserts the product, or replaces the one with the same id. */
    public function add(Product $product): void;

    /** Changes only the mode after the caller has locked and checked usage. */
    public function setClassification(string $id, bool $stock): void;

    /** Deletes a locked product after the caller has checked usage. */
    public function delete(string $id): void;

    public function get(string $id): ?Product;

    /** Locks the Catalog product row for the surrounding transaction. */
    public function getForUpdate(string $id): ?Product;

    /** @return list<Product> of one producer, ordered by name, then id */
    public function listByProducer(string $producerId): array;

    /** @return list<Product> stock products ordered by name, then id */
    public function listStock(): array;
}
