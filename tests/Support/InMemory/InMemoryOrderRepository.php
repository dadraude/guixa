<?php

namespace Tests\Support\InMemory;

use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderRepository;

/** @extends InMemoryRepository<Order> */
final class InMemoryOrderRepository extends InMemoryRepository implements OrderRepository
{
    public function add(Order $order): void
    {
        $this->save($order);
    }

    public function deleteOfCycle(string $cycleId): void
    {
        foreach ($this->all() as $order) {
            if ($order->cycleId === $cycleId) {
                $this->remove($order->id);
            }
        }
    }

    public function ofHousehold(string $cycleId, string $householdId): ?Order
    {
        foreach ($this->all() as $order) {
            if ($order->cycleId === $cycleId && $order->householdId === $householdId) {
                return $order;
            }
        }

        return null;
    }

    public function ofCycle(string $cycleId): array
    {
        return array_values(array_filter($this->all(), fn (Order $order) => $order->cycleId === $cycleId));
    }

    public function lineCountsOfHousehold(string $householdId): array
    {
        $counts = [];

        foreach ($this->all() as $order) {
            if ($order->householdId === $householdId && $order->lines() !== []) {
                $counts[$order->cycleId] = count($order->lines());
            }
        }

        return $counts;
    }

    public function lockFor(string $cycleId, string $householdId): Order
    {
        $order = $this->ofHousehold($cycleId, $householdId);

        if ($order !== null) {
            return $order;
        }

        $order = Order::start($this->nextId(), $cycleId, $householdId);
        $this->save($order);

        return $order;
    }

    /** Nothing in memory settles a cycle yet, so every line counts. */
    public function hasUnsettledLinesOf(string $productId): bool
    {
        foreach ($this->all() as $order) {
            foreach ($order->lines() as $line) {
                if ($line->productId === $productId) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
