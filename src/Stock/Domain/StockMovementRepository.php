<?php

namespace Guixa\Stock\Domain;

use Guixa\Stock\Domain\ValueObject\SignedQuantity;

/** Append-only: there is no way to change or remove a movement (R28). */
interface StockMovementRepository
{
    public function nextId(): string;

    public function add(StockMovement $movement): void;

    /** @return list<StockMovement> oldest first */
    public function forProduct(string $stockProductId): array;

    public function available(string $stockProductId): SignedQuantity;

    /** @return array<string, SignedQuantity> available stock by stock product id; products without movements are absent */
    public function availableByProduct(): array;
}
