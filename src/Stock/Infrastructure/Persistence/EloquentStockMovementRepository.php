<?php

namespace Guixa\Stock\Infrastructure\Persistence;

use DateTimeImmutable;
use Guixa\Stock\Domain\MovementType;
use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;
use Illuminate\Support\Str;

final class EloquentStockMovementRepository implements StockMovementRepository
{
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function add(StockMovement $movement): void
    {
        StockMovementModel::query()->create([
            'id' => $movement->id,
            'stock_product_id' => $movement->stockProductId,
            'type' => $movement->type->value,
            'quantity' => $movement->quantity->toString(),
            'occurred_at' => $movement->occurredAt->format(DATE_ATOM),
            'recorded_by' => $movement->recordedBy,
            'note' => $movement->note,
            'cycle_id' => $movement->cycleId,
            'order_id' => $movement->orderId,
        ]);
    }

    public function forProduct(string $stockProductId): array
    {
        $movements = StockMovementModel::query()
            ->where('stock_product_id', $stockProductId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn (StockMovementModel $model) => $this->toDomain($model))
            ->all();

        return array_values($movements);
    }

    public function hasProductReference(string $stockProductId): bool
    {
        return StockMovementModel::query()->where('stock_product_id', $stockProductId)->exists();
    }

    public function available(string $stockProductId): SignedQuantity
    {
        $total = StockMovementModel::query()
            ->where('stock_product_id', $stockProductId)
            ->selectRaw('coalesce(sum(quantity * 1000), 0)::bigint as total')
            ->value('total');

        return $this->sum($total);
    }

    public function availableByProduct(): array
    {
        $totals = StockMovementModel::query()
            ->selectRaw('stock_product_id, sum(quantity * 1000)::bigint as total')
            ->groupBy('stock_product_id')
            ->pluck('total', 'stock_product_id');

        return $totals->map(fn ($total) => $this->sum($total))->all();
    }

    private function sum(mixed $total): SignedQuantity
    {
        return SignedQuantity::ofThousandths((int) $total);
    }

    private function toDomain(StockMovementModel $model): StockMovement
    {
        return StockMovement::restore(
            $model->id,
            $model->stock_product_id,
            MovementType::from($model->type),
            SignedQuantity::fromString($model->quantity),
            new DateTimeImmutable($model->occurred_at),
            $model->recorded_by,
            $model->note,
            $model->cycle_id,
            $model->order_id,
        );
    }
}
