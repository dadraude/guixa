<?php

namespace Tests\Support\InMemory;

use DomainException;
use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;

/** @extends InMemoryRepository<StockMovement> */
final class InMemoryStockMovementRepository extends InMemoryRepository implements StockMovementRepository
{
    public function add(StockMovement $movement): void
    {
        if ($movement->correctionId !== null) {
            foreach ($this->all() as $existing) {
                if ($existing->correctionId === $movement->correctionId && $existing->productId === $movement->productId) {
                    throw new DomainException('This basket correction was already recorded for the product.');
                }
            }
        } elseif ($movement->basketId !== null) {
            foreach ($this->all() as $existing) {
                if ($existing->basketId === $movement->basketId && $existing->productId === $movement->productId) {
                    throw new DomainException('This basket sale was already recorded for the product.');
                }
            }
        }

        $this->save($movement);
    }

    public function forProduct(string $productId): array
    {
        $movements = array_values(array_filter($this->all(), fn (StockMovement $movement) => $movement->productId === $productId));
        usort($movements, fn (StockMovement $a, StockMovement $b) => [$a->occurredAt, $a->id] <=> [$b->occurredAt, $b->id]);

        return $movements;
    }

    public function hasProductReference(string $productId): bool
    {
        return $this->forProduct($productId) !== [];
    }

    public function available(string $productId): SignedQuantity
    {
        return $this->availableByProduct()[$productId] ?? SignedQuantity::ofThousandths(0);
    }

    public function availableByProduct(): array
    {
        $totals = [];

        foreach ($this->all() as $movement) {
            $totals[$movement->productId] = ($totals[$movement->productId] ?? SignedQuantity::ofThousandths(0))->plus($movement->quantity);
        }

        return $totals;
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
