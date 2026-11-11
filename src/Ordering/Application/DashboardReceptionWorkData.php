<?php

namespace Guixa\Ordering\Application;

final readonly class DashboardReceptionWorkData
{
    public function __construct(
        public string $cycleId,
        public string $producerId,
        public string $producerName,
        public string $pickupDate,
        public DashboardReceptionStage $stage,
        public bool $allocationDifference,
    ) {}
}
