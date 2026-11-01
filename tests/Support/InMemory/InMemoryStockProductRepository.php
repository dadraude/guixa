<?php

namespace Tests\Support\InMemory;

use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Domain\StockProduct;
use Guixa\Stock\Domain\StockProductRepository;
use LogicException;

/** @extends InMemoryRepository<StockProduct> */
final class InMemoryStockProductRepository extends InMemoryRepository implements StockProductRepository
{
    /** @var list<string> */
    public array $locked = [];

    public function create(string $name, string $unit, Money $price, bool $variable, ?string $containersPerUnit, string $producerId = 'fixture-producer'): StockProduct
    {
        $product = StockProduct::restore($this->nextId(), $name, $unit, $price, $variable,
            $containersPerUnit === null ? null : Quantity::fromString($containersPerUnit), true, $producerId);
        $this->add($product);

        return $product;
    }

    public function change(string $id, string $name, string $unit, Money $price, bool $variable, ?string $containersPerUnit): void
    {
        $old = $this->get($id) ?? throw new LogicException("Unknown fixture stock product: {$id}");
        $this->add(StockProduct::restore($id, $name, $unit, $price, $variable,
            $containersPerUnit === null ? null : Quantity::fromString($containersPerUnit), $old->isActive(), $old->producerId));
    }

    public function deactivate(string $id): void
    {
        $old = $this->get($id) ?? throw new LogicException("Unknown fixture stock product: {$id}");
        $this->add(StockProduct::restore($id, $old->name(), $old->unit(), $old->price(), $old->isVariable(),
            $old->containersPerUnit(), false, $old->producerId));
    }

    public function add(StockProduct $product): void
    {
        $this->save($product);
    }

    public function get(string $id): ?StockProduct
    {
        return $this->find($id);
    }

    public function lock(string $id): ?StockProduct
    {
        $this->locked[] = $id;

        return $this->get($id);
    }

    public function list(): array
    {
        $products = $this->all();
        usort($products, fn (StockProduct $a, StockProduct $b) => [mb_strtolower($a->name()), $a->id] <=> [mb_strtolower($b->name()), $b->id]);

        return $products;
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
