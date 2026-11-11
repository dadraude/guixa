<?php

namespace Guixa\Dashboard\Domain\ValueObject;

/** One validated basket still to collect; no cycle means an independent stock basket. */
final readonly class BasketWork
{
    public function __construct(
        public string $basketId,
        public ?string $cycleId,
        public ?string $scheduledDate,
    ) {}
}
