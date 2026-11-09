<?php

namespace Guixa\Ordering\Application;

final readonly class ProducerUnsentCycleData
{
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
    ) {}
}
