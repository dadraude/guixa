<?php

namespace Guixa\Ordering\Domain;

interface OrderRepository
{
    public function nextId(): string;

    /** Stores the order and its lines, replacing what was stored. */
    public function add(Order $order): void;

    /** Archives every order of a cycle by marking it cancelled; orders and lines are retained (R4b, D14). */
    public function archiveOfCycle(string $cycleId): void;

    /**
     * Stores the *will not be delivered* mark on these lines of the order, so later catalog changes do not affect
     * them (D9). Products without a line are ignored.
     *
     * @param  list<string>  $productIds
     */
    public function markNotDelivered(string $orderId, array $productIds): void;

    /** @return list<string> product ids of the order's lines stored as not delivered, sorted */
    public function notDeliveredProductIds(string $orderId): array;

    public function ofHousehold(string $cycleId, string $householdId): ?Order;

    /** @return list<Order> every household's order in the cycle, in no particular order */
    public function ofCycle(string $cycleId): array;

    /**
     * Where orders belong, cancelled ones included, for readers that only hold an order id.
     *
     * @param  list<string>  $orderIds
     * @return array<string, string> cycle id by order id
     */
    public function cycleIdsOfOrders(array $orderIds): array;

    /** @return list<string> ids of every order of the cycle, cancelled ones included, sorted */
    public function idsOfCycle(string $cycleId): array;

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

    /** Whether retained or current weekly lines reference the product. */
    public function hasWeeklyReference(string $productId): bool;

    /** Whether any order has a line of this product in a cycle that is not settled (R1). */
    public function hasUnsettledLinesOf(string $productId): bool;
}
