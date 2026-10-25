<?php

namespace Guixa\Ordering\Application;

final readonly class ContainerCountData
{
    /** @param list<HouseholdContainerCountData> $households */
    public function __construct(
        public string $cycleId,
        public int $total,
        public array $households,
    ) {}
}
