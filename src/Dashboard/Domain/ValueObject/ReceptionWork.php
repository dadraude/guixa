<?php

namespace Guixa\Dashboard\Domain\ValueObject;

use Guixa\Dashboard\Domain\ActionKind;

/** One sent producer order's reception still to work on; the pair (cycle, producer) is its identity because an unopened reception has no row. */
final readonly class ReceptionWork
{
    public function __construct(
        public string $cycleId,
        public string $producerId,
        public string $producerName,
        public string $scheduledDate,
        public ActionKind $action,
        public bool $allocationDifference,
    ) {}

    public function recordKey(): string
    {
        return "{$this->cycleId}|{$this->producerId}";
    }
}
