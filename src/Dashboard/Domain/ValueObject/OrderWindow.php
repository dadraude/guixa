<?php

namespace Guixa\Dashboard\Domain\ValueObject;

/** An open ordering cycle; each cutoff is `[producerName, closesAt]` in cooperative-local `Y-m-d H:i`. */
final readonly class OrderWindow
{
    /** @param  list<array{string, string}>  $cutoffs */
    public function __construct(
        public string $cycleId,
        public string $scheduledDate,
        public array $cutoffs,
    ) {}
}
