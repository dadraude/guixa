<?php

namespace Guixa\Stock\Infrastructure\Persistence;

use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Domain\StockProduct;
use Guixa\Stock\Domain\StockProductRepository;
use Illuminate\Support\Str;

final class EloquentStockProductRepository implements StockProductRepository
{
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function add(StockProduct $product): void
    {
        StockProductModel::query()->updateOrCreate(['id' => $product->id], [
            'name' => $product->name(),
            'unit' => $product->unit(),
            'price_cents' => $product->price()->cents,
            'variable' => $product->isVariable(),
            'containers_per_unit' => $product->containersPerUnit()?->toString(),
            'active' => $product->isActive(),
        ]);
    }

    public function get(string $id): ?StockProduct
    {
        $model = StockProductModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function list(): array
    {
        $items = StockProductModel::query()
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get()
            ->map(fn (StockProductModel $model) => $this->toDomain($model))
            ->all();

        return array_values($items);
    }

    private function toDomain(StockProductModel $model): StockProduct
    {
        return StockProduct::restore(
            $model->id,
            $model->name,
            $model->unit,
            Money::ofCents($model->price_cents),
            $model->variable,
            $model->containers_per_unit === null ? null : Quantity::fromString($model->containers_per_unit),
            $model->active,
        );
    }
}
