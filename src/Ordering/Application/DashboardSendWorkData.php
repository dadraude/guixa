<?php

namespace Guixa\Ordering\Application;

final readonly class DashboardSendWorkData
{
    public function __construct(
        public string $cycleId,
        public string $producerId,
        public string $producerName,
        public string $pickupDate,
    ) {}
}
