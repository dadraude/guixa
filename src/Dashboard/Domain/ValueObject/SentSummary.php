<?php

namespace Guixa\Dashboard\Domain\ValueObject;

/** A producer's own sent order, identified by its cycle alone. */
final readonly class SentSummary
{
    public function __construct(
        public string $cycleId,
        public string $scheduledDate,
    ) {}
}
