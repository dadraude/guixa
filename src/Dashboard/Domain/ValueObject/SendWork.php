<?php

namespace Guixa\Dashboard\Domain\ValueObject;

/** One producer order a manager can send by hand; the pair (cycle, producer) is its identity. */
final readonly class SendWork
{
    public function __construct(
        public string $cycleId,
        public string $producerId,
        public string $producerName,
        public string $scheduledDate,
    ) {}
}
