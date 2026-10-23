<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use Guixa\Ordering\Domain\Error\FractionalQuantityNotAllowed;
use Guixa\Ordering\Domain\ValueObject\OrderableProduct;
use Guixa\Shared\Domain\Quantity;

/** Everything one household has in one cycle (R7). Only the ordering part exists so far. */
final class Order
{
    /** @param array<string, OrderLine> $lines by product id */
    private function __construct(
        public readonly string $id,
        public readonly string $cycleId,
        public readonly string $householdId,
        private array $lines,
    ) {}

    public static function start(string $id, string $cycleId, string $householdId): self
    {
        return new self($id, $cycleId, $householdId, []);
    }

    /** @param list<OrderLine> $lines */
    public static function restore(string $id, string $cycleId, string $householdId, array $lines): self
    {
        $byProduct = [];

        foreach ($lines as $line) {
            $byProduct[$line->productId] = $line;
        }

        return new self($id, $cycleId, $householdId, $byProduct);
    }

    /**
     * Adds the product or changes its quantity, snapshotting the product as it is now (R10). A changed line keeps
     * its creation moment and who added it. No wallet balance is checked (R11).
     */
    public function setLine(
        OrderableProduct $product,
        Quantity $quantity,
        Cycle $cycle,
        string $lineId,
        string $addedBy,
        DateTimeImmutable $now,
    ): void {
        $cycle->assertOpenFor($product->producerId, $now);

        if (! $product->variable && ! $quantity->isWhole()) {
            throw FractionalQuantityNotAllowed::forUnit($product->name, $quantity->toString());
        }

        $existing = $this->lines[$product->id] ?? null;

        $this->lines[$product->id] = new OrderLine(
            $existing->id ?? $lineId,
            $product->id,
            $product->producerId,
            $product->name,
            $product->unit,
            $product->price,
            $product->variable,
            $product->containersPerUnit,
            $quantity,
            $quantity,
            $existing->createdAt ?? $now,
            $existing->addedBy ?? $addedBy,
        );
    }

    /** Setting the quantity to zero removes the line; a product not in the order is left alone (R9). */
    public function removeLine(string $productId, Cycle $cycle, DateTimeImmutable $now): void
    {
        $line = $this->lines[$productId] ?? null;

        if ($line === null) {
            return;
        }

        $cycle->assertOpenFor($line->producerId, $now);

        unset($this->lines[$productId]);
    }

    /** @return list<OrderLine> in the order they were added */
    public function lines(): array
    {
        $lines = array_values($this->lines);
        usort($lines, fn (OrderLine $a, OrderLine $b) => [$a->createdAt, $a->id] <=> [$b->createdAt, $b->id]);

        return $lines;
    }
}
