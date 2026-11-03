<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\HouseholdInactive;
use Guixa\Ordering\Domain\Error\HouseholdNotFound;
use Guixa\Ordering\Domain\Error\LineMarkedNotDelivered;
use Guixa\Ordering\Domain\Error\ProductNotInReception;
use Guixa\Ordering\Domain\Error\ProductNotOrderable;
use Guixa\Ordering\Domain\Households;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Support\Facades\Gate;

/** Way B: sets one active household's allocation of a product by hand, without changing the received total (R16, S13, S39, S40). */
final class SetAllocation
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly ReceptionLoader $loader,
        private readonly Households $households,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
        private readonly SalesBaskets $baskets,
    ) {}

    /** @param  string  $quantity  at most 3 decimals, zero or more; whole units for a non-variable product */
    public function __invoke(Actor $actor, string $cycleId, string $producerId, string $productId, string $householdId, string $quantity): void
    {
        Gate::forUser($actor)->authorize('manage');

        $allocated = Quantity::fromString($quantity);
        $now = $this->clock->now()->setTimezone($this->clock->timezone());

        $this->transactions->run(function () use ($actor, $cycleId, $producerId, $productId, $householdId, $allocated, $now) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->assertReceptionEditable($producerId);
            $loaded = $this->loader->load($cycle, $producerId);

            $product = $loaded->reception->product($productId) ?? throw ProductNotInReception::withId($productId);
            $this->loader->lockWeekly($productId) ?? throw ProductNotInReception::withId($productId);
            $product->assertInStep($allocated);
            $loaded->assertRedistributable($cycle, $productId);

            $household = $this->households->find([$householdId])[$householdId] ?? throw HouseholdNotFound::withId($householdId);

            if (! $household->active) {
                throw HouseholdInactive::withId($householdId);
            }

            $order = $this->orders->lockFor($cycleId, $householdId);

            // No validated basket has a line of the product (checked above), so a validated household's share
            // goes to its late weekly basket, which stays correctable like any draft (R16, R18, R19, S40).
            $lateArrival = $cycle->isSettled() && $loaded->baskets->isValidated($householdId);

            if (! $order->hasLine($productId)) {
                // A household that did not order gets a line with the product as it is now (R10, R16, S39).
                $snapshot = $loaded->catalog[$productId] ?? throw ProductNotOrderable::withId($productId);
                $order->allocateNew($snapshot, $allocated, $this->orders->nextId(), $actor->accountId, $now);
                $this->orders->add($order);
                $this->followAllocation($cycle, $order, $lateArrival ? $productId : null);

                return;
            }

            if (isset($loaded->marksByOrder[$order->id][$productId])) {
                throw LineMarkedNotDelivered::forProduct($productId);
            }

            $order->allocate($productId, $allocated);
            $this->orders->add($order);
            $this->followAllocation($cycle, $order, $lateArrival ? $productId : null);
        });
    }

    /** After settlement an unvalidated base follows the allocation at once, so the reception compares real actual quantities. */
    private function followAllocation(Cycle $cycle, Order $order, ?string $lateProductId): void
    {
        if (! $cycle->isSettled()) {
            return;
        }

        $source = WeeklySourceData::of($order);
        $lateProductId === null ? $this->baskets->syncWeeklyBase($source) : $this->baskets->addLateArrival($source, [$lateProductId]);
    }
}
