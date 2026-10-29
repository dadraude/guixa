<?php

namespace Tests\Support\InMemory;

use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;
use LogicException;

/** @extends InMemoryRepository<Product> */
final class InMemoryProductRepository extends InMemoryRepository implements ProductRepository
{
    public function add(Product $product): void
    {
        $existing = $this->get($product->id);
        if ($existing !== null && ($existing->producerId !== $product->producerId || $existing->isStock() !== $product->isStock())) {
            throw new LogicException('A product producer and mode cannot change.');
        }

        $this->save($product);
    }

    public function get(string $id): ?Product
    {
        return $this->find($id);
    }

    public function getForUpdate(string $id): ?Product
    {
        return $this->get($id);
    }

    public function listByProducer(string $producerId): array
    {
        $products = array_values(array_filter($this->all(), fn (Product $product) => $product->producerId === $producerId));
        usort($products, fn (Product $a, Product $b) => [mb_strtolower($a->name()), $a->id] <=> [mb_strtolower($b->name()), $b->id]);

        return $products;
    }

    public function listStock(): array
    {
        $products = array_values(array_filter($this->all(), fn (Product $product) => $product->isStock()));
        usort($products, fn (Product $a, Product $b) => [mb_strtolower($a->name()), $a->id] <=> [mb_strtolower($b->name()), $b->id]);

        return $products;
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
