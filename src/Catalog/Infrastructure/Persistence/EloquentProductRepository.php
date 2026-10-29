<?php

namespace Guixa\Catalog\Infrastructure\Persistence;

use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Support\Str;
use LogicException;

final class EloquentProductRepository implements ProductRepository
{
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function add(Product $product): void
    {
        $existing = ProductModel::query()->find($product->id);
        if ($existing !== null && ($existing->producer_id !== $product->producerId || $existing->stock !== $product->isStock())) {
            throw new LogicException('A product producer and mode cannot change.');
        }

        ProductModel::query()->updateOrCreate([
            'id' => $product->id,
            'producer_id' => $product->producerId,
            'stock' => $product->isStock(),
        ], [
            'name' => $product->name(),
            'unit' => $product->unit(),
            'price_cents' => $product->price()->cents,
            'variable' => $product->isVariable(),
            'containers_per_unit' => $product->containersPerUnit()?->toString(),
            'active' => $product->isActive(),
        ]);
    }

    public function get(string $id): ?Product
    {
        $model = ProductModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function getForUpdate(string $id): ?Product
    {
        $model = ProductModel::query()->whereKey($id)->lockForUpdate()->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function listByProducer(string $producerId): array
    {
        $items = ProductModel::query()
            ->where('producer_id', $producerId)
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductModel $model) => $this->toDomain($model))
            ->all();

        return array_values($items);
    }

    public function listStock(): array
    {
        return ProductModel::query()->where('stock', true)
            ->orderByRaw('lower(name)')->orderBy('id')->get()
            ->map(fn (ProductModel $model) => $this->toDomain($model))->all();
    }

    private function toDomain(ProductModel $model): Product
    {
        return Product::restore(
            $model->id,
            $model->producer_id,
            $model->name,
            $model->unit,
            Money::ofCents($model->price_cents),
            $model->variable,
            $model->containers_per_unit === null ? null : Quantity::fromString($model->containers_per_unit),
            $model->active,
            $model->stock,
        );
    }
}
