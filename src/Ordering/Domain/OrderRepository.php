<?php

namespace Guixa\Ordering\Domain;

interface OrderRepository
{
    public function nextId(): string;

    /** Stores the order and its lines, replacing what was stored. */
    public function add(Order $order): void;

    public function ofHousehold(string $cycleId, string $householdId): ?Order;

    /**
     * The household's order in the cycle, created empty if it has none, and locked until the surrounding
     * transaction ends, so two members adding the first lines at once share one order.
     */
    public function lockFor(string $cycleId, string $householdId): Order;
}
