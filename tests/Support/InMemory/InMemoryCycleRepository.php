<?php

namespace Tests\Support\InMemory;

use DateTimeInterface;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;

/** @extends InMemoryRepository<Cycle> */
final class InMemoryCycleRepository extends InMemoryRepository implements CycleRepository
{
    public function add(Cycle $cycle): void
    {
        $this->save($cycle);
    }

    public function get(string $id): ?Cycle
    {
        return $this->find($id);
    }

    public function getForUpdate(string $id): ?Cycle
    {
        return $this->find($id);
    }

    public function list(): array
    {
        $cycles = $this->all();
        usort($cycles, fn (Cycle $a, Cycle $b) => $a->pickupDate()->format('Y-m-d') <=> $b->pickupDate()->format('Y-m-d'));

        return $cycles;
    }

    public function existsOnPickupDate(DateTimeInterface $pickupDate): bool
    {
        foreach ($this->all() as $cycle) {
            if ($cycle->pickupDate()->format('Y-m-d') === $pickupDate->format('Y-m-d')) {
                return true;
            }
        }

        return false;
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
