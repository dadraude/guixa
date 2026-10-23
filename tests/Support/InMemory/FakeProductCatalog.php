<?php

namespace Tests\Support\InMemory;

use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Ordering\Domain\ValueObject\OrderableProduct;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Support\Str;

final class FakeProductCatalog implements ProductCatalog
{
    /** @var array<string, OrderableProduct> */
    private array $orderable = [];

    /** @return string the new product's id */
    public function offer(string $producerId, string $name, string $unit, int $priceCents, bool $variable, ?string $containersPerUnit = null): string
    {
        $id = (string) Str::uuid7();
        $this->orderable[$id] = new OrderableProduct(
            $id, $producerId, $name, $unit, Money::ofCents($priceCents), $variable,
            $containersPerUnit === null ? null : Quantity::fromString($containersPerUnit),
        );

        return $id;
    }

    public function change(string $productId, ?string $name = null, ?int $priceCents = null): void
    {
        $product = $this->orderable[$productId];
        $this->orderable[$productId] = new OrderableProduct(
            $product->id, $product->producerId, $name ?? $product->name, $product->unit,
            $priceCents === null ? $product->price : Money::ofCents($priceCents), $product->variable, $product->containersPerUnit,
        );
    }

    public function deactivate(string $productId): void
    {
        unset($this->orderable[$productId]);
    }

    public function orderable(string $productId): ?OrderableProduct
    {
        return $this->orderable[$productId] ?? null;
    }

    public function orderableOf(string $producerId): array
    {
        $products = array_filter($this->orderable, fn (OrderableProduct $product) => $product->producerId === $producerId);
        usort($products, fn (OrderableProduct $a, OrderableProduct $b) => $a->name <=> $b->name);

        return $products;
    }
}
