<?php

namespace Guixa\Dashboard\Application;

final readonly class AttentionGroupData
{
    /** @param string $action an ActionKind value */
    public function __construct(
        public string $key,
        public string $action,
        public ?string $cycleId,
        public ?string $scheduledDate,
        public int $count,
        public DestinationData $destination,
    ) {}
}
