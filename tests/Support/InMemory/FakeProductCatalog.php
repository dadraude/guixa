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

    /** @var array<string, OrderableProduct> */
    private array $deactivated = [];

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
        $this->deactivated[$productId] = $this->orderable[$productId];
        unset($this->orderable[$productId]);
    }

    public function reactivate(string $productId): void
    {
        $this->orderable[$productId] = $this->deactivated[$productId];
        unset($this->deactivated[$productId]);
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

    public function allOf(string $producerId): array
    {
        $products = array_filter(
            [...$this->orderable, ...$this->deactivated],
            fn (OrderableProduct $product) => $product->producerId === $producerId,
        );
        usort($products, fn (OrderableProduct $a, OrderableProduct $b) => $a->name <=> $b->name);

        return $products;
    }
}
