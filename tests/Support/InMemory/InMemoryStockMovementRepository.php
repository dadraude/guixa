<?php

namespace Tests\Support\InMemory;

use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;

/** @extends InMemoryRepository<StockMovement> */
final class InMemoryStockMovementRepository extends InMemoryRepository implements StockMovementRepository
{
    public function add(StockMovement $movement): void
    {
        $this->save($movement);
    }

    public function forProduct(string $stockProductId): array
    {
        $movements = array_values(array_filter($this->all(), fn (StockMovement $movement) => $movement->stockProductId === $stockProductId));
        usort($movements, fn (StockMovement $a, StockMovement $b) => [$a->occurredAt, $a->id] <=> [$b->occurredAt, $b->id]);

        return $movements;
    }

    public function hasProductReference(string $stockProductId): bool
    {
        return $this->forProduct($stockProductId) !== [];
    }

    public function available(string $stockProductId): SignedQuantity
    {
        return $this->availableByProduct()[$stockProductId] ?? SignedQuantity::ofThousandths(0);
    }

    public function availableByProduct(): array
    {
        $totals = [];

        foreach ($this->all() as $movement) {
            $totals[$movement->stockProductId] = ($totals[$movement->stockProductId] ?? SignedQuantity::ofThousandths(0))->plus($movement->quantity);
        }

        return $totals;
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
