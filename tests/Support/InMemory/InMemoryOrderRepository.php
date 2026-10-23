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

    public function ofHousehold(string $cycleId, string $householdId): ?Order
    {
        foreach ($this->all() as $order) {
            if ($order->cycleId === $cycleId && $order->householdId === $householdId) {
                return $order;
            }
        }

        return null;
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
