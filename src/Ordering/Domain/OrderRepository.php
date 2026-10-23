<?php

namespace Guixa\Ordering\Domain;

interface OrderRepository
{
    public function nextId(): string;

    /** Stores the order and its lines, replacing what was stored. */
    public function add(Order $order): void;

    public function ofHousehold(string $cycleId, string $householdId): ?Order;

    /** @return list<Order> every household's order in the cycle, in no particular order */
    public function ofCycle(string $cycleId): array;

    /**
     * How many lines the household has in each cycle, by cycle id; a cycle where it has no line is absent.
     *
     * @return array<string, int>
     */
    public function lineCountsOfHousehold(string $householdId): array;

    /**
     * The household's order in the cycle, created empty if it has none, and locked until the surrounding
     * transaction ends, so two members adding the first lines at once share one order.
     */
    public function lockFor(string $cycleId, string $householdId): Order;

    /** Whether any order has a line of this product in a cycle that is not settled (R1). */
    public function hasUnsettledLinesOf(string $productId): bool;
}
