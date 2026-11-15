<?php

namespace Guixa\Sales\Application;

final readonly class WeeklyBaseData
{
    /** @param list<WeeklyBaseLineData> $lines */
    public function __construct(
        public string $sourceOrderId,
        public string $cycleId,
        public string $householdId,
        public string $householdName,
        public array $lines,
    ) {}
}
