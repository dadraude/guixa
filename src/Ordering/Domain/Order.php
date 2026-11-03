<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use Guixa\Ordering\Domain\Error\FractionalQuantityNotAllowed;
use Guixa\Ordering\Domain\Error\InvalidLineQuantity;
use Guixa\Ordering\Domain\Error\LineNotMarked;
use Guixa\Ordering\Domain\Error\NotEnoughStock;
use Guixa\Ordering\Domain\Error\PickupAlreadyOpen;
use Guixa\Ordering\Domain\Error\PickupNotPending;
use Guixa\Ordering\Domain\Error\ProductNotInPickup;
use Guixa\Ordering\Domain\Error\ReturnLineNotInPickup;
use Guixa\Ordering\Domain\Error\StockLineNotInPickup;
use Guixa\Ordering\Domain\Error\StockProductInactive;
use Guixa\Ordering\Domain\ValueObject\OrderableProduct;
use Guixa\Ordering\Domain\ValueObject\ReturnItemOffer;
use Guixa\Ordering\Domain\ValueObject\StockOffer;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use LogicException;

/** Everything one household has in one cycle (R7). The ordering, reception and pickup-adjustment parts exist so far. */
final class Order
{
    /**
     * @param  array<string, OrderLine>  $lines  by product id
     * @param  array<string, StockLine>  $stockLines  by stock product id
     * @param  array<string, ReturnLine>  $returnLines  by return item id
     * @param  list<PickupAdjustment>  $adjustments
     */
    private function __construct(
        public readonly string $id,
        public readonly string $cycleId,
        public readonly string $householdId,
        private array $lines,
        private PickupStatus $pickup = PickupStatus::None,
        private array $stockLines = [],
        private array $returnLines = [],
        private ?DateTimeImmutable $pickupDoneAt = null,
        private ?Money $charged = null,
        private array $adjustments = [],
    ) {}

    public static function start(string $id, string $cycleId, string $householdId): self
    {
        return new self($id, $cycleId, $householdId, []);
    }

    /**
     * @param  list<OrderLine>  $lines
     * @param  list<StockLine>  $stockLines
     * @param  list<ReturnLine>  $returnLines
     * @param  list<PickupAdjustment>  $adjustments
     */
    public static function restore(string $id, string $cycleId, string $householdId, array $lines, PickupStatus $pickup = PickupStatus::None, array $stockLines = [], array $returnLines = [], ?DateTimeImmutable $pickupDoneAt = null, ?Money $charged = null, array $adjustments = []): self
    {
        $byProduct = [];

        foreach ($lines as $line) {
            $byProduct[$line->productId] = $line;
        }

        $byStockProduct = [];

        foreach ($stockLines as $line) {
            $byStockProduct[$line->stockProductId] = $line;
        }

        $byReturnItem = [];

        foreach ($returnLines as $line) {
            $byReturnItem[$line->returnItemId] = $line;
        }

        return new self($id, $cycleId, $householdId, $byProduct, $pickup, $byStockProduct, $byReturnItem, $pickupDoneAt, $charged, $adjustments);
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
        $this->assertNotFrozen();
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
        $this->assertNotFrozen();
        $line = $this->lines[$productId] ?? throw new LogicException("The order has no line of product {$productId} to allocate.");

        $this->assertInStep($line->name, $line->variable, $allocated);

        $this->lines[$productId] = $line->withAllocation($allocated, byHand: true);
    }

    /**
     * Adds an allocation-only line for a product the household did not order, with the product as it is now (R10, R16, S39).
     */
    public function allocateNew(OrderableProduct $product, Quantity $allocated, string $lineId, string $addedBy, DateTimeImmutable $now): void
    {
        $this->assertNotFrozen();
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
        $this->assertNotFrozen();
        $line = $this->lines[$productId] ?? throw new LogicException("The order has no line of product {$productId} to allocate.");

        $this->lines[$productId] = $line->withAllocation($allocated, byHand: false);
    }

    /**
     * Settlement: drops the lines marked *will not be delivered*, which include those of producers that do not
     * participate. Sales owns what happens next: the base basket and its collection (R18, R24).
     *
     * @param  array<string, true>  $marked  product ids of the marked lines (`NotDeliveredMarks::of`)
     */
    public function drop(array $marked): void
    {
        foreach ($this->lines as $productId => $line) {
            if (isset($marked[$productId])) {
                $this->lines[$productId] = $line->asDropped();
            }
        }
    }

    /** @return list<PickupAdjustment> in recording order */
    public function adjustments(): array
    {
        return $this->adjustments;
    }

    public function effectiveAllocated(OrderLine $line): Quantity
    {
        return $this->latestTarget($line->productId) ?? $line->allocated;
    }

    public function effectiveDelivered(OrderLine $line): Quantity
    {
        return $this->latestTarget($line->productId) ?? $line->delivered();
    }

    /** Only reception redistribution may append corrections; the original pickup remains historical. */
    public function appendAdjustment(string $productId, Quantity $target, string $id, DateTimeImmutable $now, string $actorId): ?PickupAdjustment
    {
        if (! $this->isFrozen()) {
            throw new LogicException('Pickup adjustments require a done pickup.');
        }

        $line = $this->lines[$productId] ?? throw new LogicException('The pickup has no product to adjust.');
        $previous = $this->effectiveDelivered($line);

        if ($previous->equals($target) && $this->effectiveAllocated($line)->equals($target)) {
            return null;
        }

        $this->assertInStep($line->name, $line->variable, $target);
        $adjustment = new PickupAdjustment(
            $id, $productId, $line->name, $line->unit, $line->unitPrice,
            $target->thousandths - $previous->thousandths,
            $line->unitPrice->multiply($target)->subtract($line->unitPrice->multiply($previous)),
            $target, $now, $actorId,
        );
        $this->adjustments[] = $adjustment;

        return $adjustment;
    }

    private function latestTarget(string $productId): ?Quantity
    {
        foreach (array_reverse($this->adjustments) as $adjustment) {
            if ($adjustment->productId === $productId) {
                return $adjustment->target;
            }
        }

        return null;
    }

    public function isFrozen(): bool
    {
        return in_array($this->pickup, [PickupStatus::Completed, PickupStatus::NotCollected], true);
    }

    public function reopenIfAllocated(): void
    {
        if (in_array($this->pickup, [PickupStatus::NothingToCollect, PickupStatus::None], true) && $this->hasAllocation()) {
            $this->pickup = PickupStatus::Pending;
        }
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
     * Freezes the pickup and returns what the household is charged: a negative total is a credit (R22, R23). The caller sells the
     * stock lines and records the movement in the same transaction.
     */
    public function complete(DateTimeImmutable $now): Money
    {
        $this->assertPending();

        $this->pickup = PickupStatus::Completed;
        $this->pickupDoneAt = $now;
        $this->charged = $this->total();

        return $this->charged;
    }

    public function markNotCollected(DateTimeImmutable $now): Money
    {
        $this->assertPending();
        $this->stockLines = [];
        $this->returnLines = [];
        $this->pickup = PickupStatus::NotCollected;
        $this->pickupDoneAt = $now;
        $this->charged = $this->total();

        return $this->charged;
    }

    public function pickupDoneAt(): ?DateTimeImmutable
    {
        return $this->pickupDoneAt;
    }

    /** What the completed pickup charged, or null while it has not been completed. */
    public function charged(): ?Money
    {
        return $this->charged;
    }

    /**
     * Records what the household takes home of a product, in the product's step; the allocation stays as the
     * reception left it (R19, D16).
     */
    public function adjustDelivered(string $productId, Quantity $delivered): void
    {
        $this->assertPending();

        $line = $this->lines[$productId] ?? null;

        if ($line === null || $line->dropped) {
            throw ProductNotInPickup::withId($productId);
        }

        $this->assertInStep($line->name, $line->variable, $delivered);

        $this->lines[$productId] = $line->withDelivered($delivered);
    }

    /**
     * Adds stock to the pickup, taking a snapshot of the product as it is now (R20). Adding a product that is already on the pickup
     * adds to its quantity and keeps the line's snapshot; the combined quantity cannot exceed what is on hand. A deactivated product cannot be added.
     * What other pickups took is checked again at completion (R22, S45).
     */
    public function addStock(StockOffer $offer, Quantity $quantity, string $lineId): void
    {
        $this->assertPending();

        if ($quantity->isZero()) {
            throw InvalidLineQuantity::mustBePositive();
        }

        $this->assertInStep($offer->name, $offer->variable, $quantity);

        if (! $offer->active) {
            throw StockProductInactive::named($offer->name);
        }

        $existing = $this->stockLines[$offer->id] ?? null;
        $combined = Quantity::ofThousandths($quantity->thousandths + ($existing->quantity->thousandths ?? 0));

        if ($combined->thousandths > $offer->available->thousandths) {
            throw NotEnoughStock::forProduct($offer->name, $offer->available->toString(), $combined->toString());
        }

        $this->stockLines[$offer->id] = $existing?->withQuantity($combined) ?? new StockLine(
            $lineId,
            $offer->id,
            $offer->name,
            $offer->unit,
            $offer->price,
            $offer->variable,
            $offer->containersPerUnit,
            $combined,
        );
    }

    /** Removes the whole stock line, even if its product was deactivated since (R20, S55). */
    public function removeStock(string $stockProductId): void
    {
        $this->assertPending();

        if (! isset($this->stockLines[$stockProductId])) {
            throw StockLineNotInPickup::withId($stockProductId);
        }

        unset($this->stockLines[$stockProductId]);
    }

    /**
     * Adds returned items to the pickup, taking a snapshot of the item's amount as it is now (R20a). Adding an item that is already on
     * the pickup adds to its quantity and keeps the line's amount. A deactivated item can still be added.
     */
    public function addReturn(ReturnItemOffer $offer, int $quantity, string $lineId): void
    {
        $this->assertPending();

        if ($quantity <= 0) {
            throw InvalidLineQuantity::mustBePositive();
        }

        $existing = $this->returnLines[$offer->id] ?? null;
        $combined = $quantity + ($existing->quantity ?? 0);

        $this->returnLines[$offer->id] = $existing?->withQuantity($combined) ?? new ReturnLine($lineId, $offer->id, $offer->name, $offer->amount, $combined);
    }

    /** Removes the whole return line. */
    public function removeReturn(string $returnItemId): void
    {
        $this->assertPending();

        if (! isset($this->returnLines[$returnItemId])) {
            throw ReturnLineNotInPickup::withId($returnItemId);
        }

        unset($this->returnLines[$returnItemId]);
    }

    /** @return list<StockLine> in the order they were added */
    public function stockLines(): array
    {
        return array_values($this->stockLines);
    }

    /** @return list<ReturnLine> in the order they were added */
    public function returnLines(): array
    {
        return array_values($this->returnLines);
    }

    /** @return list<OrderLine> the lines the household collects: dropped lines are never delivered nor charged (R18) */
    public function pickupLines(): array
    {
        return array_values(array_filter($this->lines(), fn (OrderLine $line) => ! $line->dropped));
    }

    /** What the pickup costs so far: its lines plus the stock lines, minus the return lines, each rounded half up (R22). */
    public function total(): Money
    {
        $total = array_reduce(
            $this->pickupLines(),
            fn (Money $total, OrderLine $line) => $total->add($line->amount()),
            Money::ofCents(0),
        );

        foreach ($this->stockLines as $line) {
            $total = $total->add($line->amount());
        }

        foreach ($this->returnLines as $line) {
            $total = $total->subtract($line->credit());
        }

        foreach ($this->adjustments as $adjustment) {
            $total = $total->add($adjustment->amount);
        }

        return $total;
    }

    public function lineOf(string $productId): OrderLine
    {
        return $this->lines[$productId] ?? throw new LogicException("The order has no line of product {$productId}.");
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

    private function assertNotFrozen(): void
    {
        if ($this->isFrozen()) {
            throw PickupNotPending::forOrder($this->id);
        }
    }

    private function assertPending(): void
    {
        if ($this->pickup !== PickupStatus::Pending) {
            throw PickupNotPending::forOrder($this->id);
        }
    }

    private function assertInStep(string $name, bool $variable, Quantity $quantity): void
    {
        if (! $variable && ! $quantity->isWhole()) {
            throw FractionalQuantityNotAllowed::forUnit($name, $quantity->toString());
        }
    }
}
