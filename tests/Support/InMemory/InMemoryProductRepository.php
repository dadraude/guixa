<?php

namespace Tests\Support\InMemory;

use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;
use LogicException;

/** @extends InMemoryRepository<Product> */
final class InMemoryProductRepository extends InMemoryRepository implements ProductRepository
{
    /** @var list<string> */
    public array $locked = [];

    public function add(Product $product): void
    {
        $existing = $this->get($product->id);
        if ($existing !== null && ($existing->producerId !== $product->producerId || $existing->isStock() !== $product->isStock())) {
            throw new LogicException('A product producer and mode cannot change.');
        }

        $this->save($product);
    }

    public function setClassification(string $id, bool $stock): void
    {
        $product = $this->get($id);
        if ($product === null) {
            return;
        }
        $this->save(Product::restore(
            $product->id, $product->producerId, $product->name(), $product->unit(), $product->price(),
            $product->isVariable(), $product->containersPerUnit(), $product->isActive(), $stock,
        ));
    }

    public function delete(string $id): void
    {
        $this->remove($id);
    }

    public function get(string $id): ?Product
    {
        return $this->find($id);
    }

    public function getForUpdate(string $id): ?Product
    {
        $this->locked[] = $id;

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
