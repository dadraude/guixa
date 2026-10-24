<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use Guixa\Ordering\Domain\Error\FractionalQuantityNotAllowed;
use Guixa\Ordering\Domain\Error\LineNotMarked;
use Guixa\Ordering\Domain\Error\PickupAlreadyOpen;
use Guixa\Ordering\Domain\Error\PickupNotPending;
use Guixa\Ordering\Domain\Error\ProductNotInPickup;
use Guixa\Ordering\Domain\ValueObject\OrderableProduct;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use LogicException;

/** Everything one household has in one cycle (R7). The ordering, reception and pickup-adjustment parts exist so far. */
final class Order
{
    /** @param array<string, OrderLine> $lines by product id */
    private function __construct(
        public readonly string $id,
        public readonly string $cycleId,
        public readonly string $householdId,
        private array $lines,
        private PickupStatus $pickup = PickupStatus::None,
    ) {}

    public static function start(string $id, string $cycleId, string $householdId): self
    {
        return new self($id, $cycleId, $householdId, []);
    }

    /** @param list<OrderLine> $lines */
    public static function restore(string $id, string $cycleId, string $householdId, array $lines, PickupStatus $pickup = PickupStatus::None): self
    {
        $byProduct = [];

        foreach ($lines as $line) {
            $byProduct[$line->productId] = $line;
        }

        return new self($id, $cycleId, $householdId, $byProduct, $pickup);
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

    /**
     * Removes a line marked *will not be delivered*, which stays allowed after its producer's cutoff (R3b, R8).
     * A product that is not in the order is left alone, like a zero quantity.
     *
     * @param  array<string, true>  $marked  product ids of the marked lines (`NotDeliveredMarks::of`)
     */
    public function removeMarkedLine(string $productId, array $marked): void
    {
        if (! isset($this->lines[$productId])) {
            return;
        }

        if (! isset($marked[$productId])) {
            throw LineNotMarked::forProduct($productId);
        }

        unset($this->lines[$productId]);
    }

    /**
     * Sets what the household receives of a product it already has a line for, by hand (R16). The line keeps its
     * ordered quantity; whole units for a non-variable product (R9).
     */
    public function allocate(string $productId, Quantity $allocated): void
    {
        $line = $this->lines[$productId] ?? throw new LogicException("The order has no line of product {$productId} to allocate.");

        $this->assertInStep($line->name, $line->variable, $allocated);

        $this->lines[$productId] = $line->withAllocation($allocated, byHand: true);
    }

    /**
     * Adds an allocation-only line for a product the household did not order, with the product as it is now (R10, R16, S39).
     */
    public function allocateNew(OrderableProduct $product, Quantity $allocated, string $lineId, string $addedBy, DateTimeImmutable $now): void
    {
        $this->assertInStep($product->name, $product->variable, $allocated);

        $this->lines[$product->id] = (new OrderLine(
            $lineId,
            $product->id,
            $product->producerId,
            $product->name,
            $product->unit,
            $product->price,
            $product->variable,
            $product->containersPerUnit,
            Quantity::ofThousandths(0),
            $allocated,
            $now,
            $addedBy,
        ))->withAllocation($allocated, byHand: true);
    }

    /** Sets the allocation the reception computed from the ordered quantities, clearing the manual mark (R15). */
    public function reallocate(string $productId, Quantity $allocated): void
    {
        $line = $this->lines[$productId] ?? throw new LogicException("The order has no line of product {$productId} to allocate.");

        $this->lines[$productId] = $line->withAllocation($allocated, byHand: false);
    }

    /**
     * Settlement: drops the lines marked *will not be delivered*, which include those of producers that do not
     * participate, and opens the pickup: pending when anything is allocated, nothing to collect when every allocation
     * is 0 (R18, R24). An order without any line has no pickup.
     *
     * @param  array<string, true>  $marked  product ids of the marked lines (`NotDeliveredMarks::of`)
     */
    public function drop(array $marked): void
    {
        if ($this->lines === []) {
            return;
        }

        foreach ($this->lines as $productId => $line) {
            if (isset($marked[$productId])) {
                $this->lines[$productId] = $line->asDropped();
            }
        }

        $this->pickup = $this->hasAllocation() ? PickupStatus::Pending : PickupStatus::NothingToCollect;
    }

    public function pickup(): PickupStatus
    {
        return $this->pickup;
    }

    /** A manager opens the pickup of a household that has no allocation to collect, such as a stock-only one (R19, S18). */
    public function openPickup(): void
    {
        if ($this->pickup !== PickupStatus::None) {
            throw PickupAlreadyOpen::forOrder($this->id);
        }

        $this->pickup = PickupStatus::Pending;
    }

    /**
     * Records what the household takes home of a product, in the product's step; the allocation stays as the
     * reception left it (R19, D16).
     */
    public function adjustDelivered(string $productId, Quantity $delivered): void
    {
        if ($this->pickup !== PickupStatus::Pending) {
            throw PickupNotPending::forOrder($this->id);
        }

        $line = $this->lines[$productId] ?? null;

        if ($line === null || $line->dropped) {
            throw ProductNotInPickup::withId($productId);
        }

        $this->assertInStep($line->name, $line->variable, $delivered);

        $this->lines[$productId] = $line->withDelivered($delivered);
    }

    /** @return list<OrderLine> the lines the household collects: dropped lines are never delivered nor charged (R18) */
    public function pickupLines(): array
    {
        return array_values(array_filter($this->lines(), fn (OrderLine $line) => ! $line->dropped));
    }

    /** What the pickup costs so far: the sum of its lines, each rounded half up (R22). */
    public function total(): Money
    {
        return array_reduce(
            $this->pickupLines(),
            fn (Money $total, OrderLine $line) => $total->add($line->amount()),
            Money::ofCents(0),
        );
    }

    public function hasLine(string $productId): bool
    {
        return isset($this->lines[$productId]);
    }

    /** @return list<OrderLine> the lines the household ordered: allocation-only lines are not part of what it asked for (R16) */
    public function orderedLines(): array
    {
        return array_values(array_filter($this->lines(), fn (OrderLine $line) => $line->isOrdered()));
    }

    /** @return list<OrderLine> in the order they were added */
    public function lines(): array
    {
        $lines = array_values($this->lines);
        usort($lines, fn (OrderLine $a, OrderLine $b) => [$a->createdAt, $a->id] <=> [$b->createdAt, $b->id]);

        return $lines;
    }

    private function hasAllocation(): bool
    {
        foreach ($this->lines as $line) {
            if (! $line->dropped && ! $line->allocated->isZero()) {
                return true;
            }
        }

        return false;
    }

    private function assertInStep(string $name, bool $variable, Quantity $quantity): void
    {
        if (! $variable && ! $quantity->isWhole()) {
            throw FractionalQuantityNotAllowed::forUnit($name, $quantity->toString());
        }
    }
}
