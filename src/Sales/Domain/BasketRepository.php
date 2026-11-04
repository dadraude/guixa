<?php

namespace Guixa\Sales\Domain;

interface BasketRepository
{
    public function nextId(): string;

    public function save(Basket $basket): void;

    public function get(string $id): ?Basket;

    /** The base basket of a source order, never a late one. */
    public function getBySourceOrder(string $orderId): ?Basket;

    /** The source order's late weekly basket that is not validated yet (draft, or nothing to collect after a correction to zero), if any. */
    public function getOpenLateBySourceOrder(string $orderId): ?Basket;

    /** @return list<Basket> every weekly basket attributed to the cycle, base and late, by id */
    public function weeklyOfCycle(string $cycleId): array;

    public function lock(string $id): ?Basket;

    /** @return list<Basket> */
    public function list(): array;

    public function getForHousehold(string $id, string $householdId): ?Basket;

    /** @return list<Basket> */
    public function listForHousehold(string $householdId): array;

    /**
     * Whole validated and collected baskets of the households, in a fixed number of queries.
     *
     * @param  list<string>  $householdIds
     * @return list<Basket>
     */
    public function settledForHouseholds(array $householdIds): array;

    public function hasProductHistory(string $productId): bool;
}
