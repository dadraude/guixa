<?php

namespace Guixa\Stock\Infrastructure\Persistence;

use Guixa\Shared\Domain\Money;
use Guixa\Stock\Domain\ReturnItem;
use Guixa\Stock\Domain\ReturnItemRepository;
use Illuminate\Support\Str;

final class EloquentReturnItemRepository implements ReturnItemRepository
{
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function add(ReturnItem $item): void
    {
        ReturnItemModel::query()->updateOrCreate(['id' => $item->id], [
            'name' => $item->name(),
            'amount_cents' => $item->amount()->cents,
            'active' => $item->isActive(),
        ]);
    }

    public function get(string $id): ?ReturnItem
    {
        $model = ReturnItemModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function list(): array
    {
        $items = ReturnItemModel::query()
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get()
            ->map(fn (ReturnItemModel $model) => $this->toDomain($model))
            ->all();

        return array_values($items);
    }

    private function toDomain(ReturnItemModel $model): ReturnItem
    {
        return ReturnItem::restore($model->id, $model->name, Money::ofCents($model->amount_cents), $model->active);
    }
}
