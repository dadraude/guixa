<?php

namespace Tests\Support\InMemory;

use Guixa\Ordering\Domain\ReceivedTotal;
use Guixa\Ordering\Domain\ReceivedTotalRepository;

/** @extends InMemoryRepository<ReceivedTotal> */
final class InMemoryReceivedTotalRepository extends InMemoryRepository implements ReceivedTotalRepository
{
    public function set(ReceivedTotal $total): void
    {
        $this->save($total);
    }

    public function ofProducer(string $cycleId, string $producerId): array
    {
        $totals = [];

        foreach ($this->all() as $total) {
            if ($total->cycleId === $cycleId && $total->producerId === $producerId) {
                $totals[$total->productId] = $total->received;
            }
        }

        return $totals;
    }

    protected function idOf(object $item): string
    {
        return $item->cycleId.'|'.$item->productId;
    }
}
