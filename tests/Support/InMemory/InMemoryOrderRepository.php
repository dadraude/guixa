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
                unset($this->notDelivered[$order->id]);
            }
        }
    }

    /** @var array<string, array<string, true>> stored marks, by order id then product id */
    private array $notDelivered = [];

    public function markNotDelivered(string $orderId, array $productIds): void
    {
        $order = $this->find($orderId);
        $present = $order === null ? [] : array_map(fn ($line) => $line->productId, $order->lines());

        foreach (array_intersect($productIds, $present) as $productId) {
            $this->notDelivered[$orderId][$productId] = true;
        }
    }

    public function notDeliveredProductIds(string $orderId): array
    {
        $ids = array_keys($this->notDelivered[$orderId] ?? []);
        sort($ids);

        return $ids;
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
