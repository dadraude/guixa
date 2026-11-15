<?php

namespace Guixa\Stock\Infrastructure\Persistence;

use DateTimeImmutable;
use Guixa\Stock\Domain\MovementType;
use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentStockMovementRepository implements StockMovementRepository
{
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function add(StockMovement $movement): void
    {
        // A savepoint keeps the caller's transaction usable after a rejected duplicate sale.
        DB::transaction(fn () => StockMovementModel::query()->create([
            'id' => $movement->id,
            'product_id' => $movement->productId,
            'type' => $movement->type->value,
            'quantity' => $movement->quantity->toString(),
            'occurred_at' => $movement->occurredAt->format(DATE_ATOM),
            'recorded_by' => $movement->recordedBy,
            'note' => $movement->note,
            'cycle_id' => $movement->cycleId,
            'basket_id' => $movement->basketId,
            'correction_id' => $movement->correctionId,
        ]));
    }

    public function forProduct(string $productId): array
    {
        $movements = StockMovementModel::query()
            ->where('product_id', $productId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn (StockMovementModel $model) => $this->toDomain($model))
            ->all();

        return array_values($movements);
    }

    public function hasProductReference(string $productId): bool
    {
        return StockMovementModel::query()->where('product_id', $productId)->exists();
    }

    public function available(string $productId): SignedQuantity
    {
        $total = StockMovementModel::query()
            ->where('product_id', $productId)
            ->selectRaw('coalesce(sum(quantity * 1000), 0)::bigint as total')
            ->value('total');

        return $this->sum($total);
    }

    public function availableByProduct(): array
    {
        $totals = StockMovementModel::query()
            ->selectRaw('product_id, sum(quantity * 1000)::bigint as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

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
            $model->product_id,
            MovementType::from($model->type),
            SignedQuantity::fromString($model->quantity),
            new DateTimeImmutable($model->occurred_at),
            $model->recorded_by,
            $model->note,
            $model->cycle_id,
            $model->basket_id,
            $model->correction_id,
        );
    }
}
