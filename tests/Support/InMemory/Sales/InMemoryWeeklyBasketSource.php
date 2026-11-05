<?php

namespace Tests\Support\InMemory\Sales;

use Guixa\Sales\Application\WeeklyBasketSource;

/** A weekly source that knows which cycle each source order belongs to and records the lock and closure calls. */
final class InMemoryWeeklyBasketSource implements WeeklyBasketSource
{
    /** @var list<string> */
    public array $calls = [];

    /** @param array<string, string> $cycles cycle id by source order id */
    public function __construct(public array $cycles = []) {}

    public function place(string $sourceOrderId, string $cycleId): void
    {
        $this->cycles[$sourceOrderId] = $cycleId;
    }

    public function lockSettled(string $cycleId): void
    {
        $this->calls[] = "lock:{$cycleId}";
    }

    public function closeIfFinished(string $cycleId): void
    {
        $this->calls[] = "close:{$cycleId}";
    }

    public function cyclesOf(array $sourceOrderIds): array
    {
        return array_intersect_key($this->cycles, array_flip($sourceOrderIds));
    }

    public function ofCycle(string $cycleId): array
    {
        return array_keys(array_filter($this->cycles, fn (string $cycle) => $cycle === $cycleId));
    }
}
