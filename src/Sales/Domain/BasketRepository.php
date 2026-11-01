<?php

namespace Guixa\Sales\Domain;

interface BasketRepository
{
    public function nextId(): string;

    public function save(Basket $basket): void;

    public function get(string $id): ?Basket;

    public function lock(string $id): ?Basket;

    /** @return list<Basket> */
    public function list(): array;

    public function getForHousehold(string $id, string $householdId): ?Basket;

    /** @return list<Basket> */
    public function listForHousehold(string $householdId): array;

    public function hasProductHistory(string $productId): bool;
}
