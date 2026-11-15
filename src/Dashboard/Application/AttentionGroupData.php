<?php

namespace Guixa\Dashboard\Application;

final readonly class AttentionGroupData
{
    /**
     * @param  string  $action  an ActionKind value
     * @param  ?string  $producerName  the producer, only when the group is that one reception
     * @param  int  $allocationDifferenceCount  how many of the group's receptions have an allocation that no longer matches what was received
     */
    public function __construct(
        public string $key,
        public string $action,
        public ?string $cycleId,
        public ?string $scheduledDate,
        public int $count,
        public DestinationData $destination,
        public ?string $producerName = null,
        public int $allocationDifferenceCount = 0,
    ) {}
}
