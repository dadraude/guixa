<?php

namespace Guixa\Ordering\Application;

/** A cycle as a member's household sees it in the list (R4a, R23a). */
final readonly class MemberCycleData
{
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public bool $open,
        public int $lineCount,
    ) {}
}
