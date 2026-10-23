<?php

namespace Guixa\Ordering\Domain;

use DateTimeInterface;

interface CycleRepository
{
    public function nextId(): string;

    /** Inserts the cycle or replaces its producers' participation. */
    public function add(Cycle $cycle): void;

    public function get(string $id): ?Cycle;

    /** Locks the cycle until the surrounding transaction ends, so two changes to it cannot interleave. */
    public function getForUpdate(string $id): ?Cycle;

    /** Locks the cycle against changes until the transaction ends, while letting other readers in (R6b, R8). */
    public function getForShare(string $id): ?Cycle;

    /** @return list<Cycle> by pickup date, earliest first */
    public function list(): array;

    /** Whether a cycle (cancelled ones do not count) has this pickup date (R4). */
    public function existsOnPickupDate(DateTimeInterface $pickupDate): bool;
}
