<?php

namespace Guixa\Dashboard\Application;

/** The existing screen a group leads to: that basket's detail when it is the only one, otherwise the pending list; staff-wide or the member's own. */
final readonly class DestinationData
{
    public function __construct(
        public bool $staffWide,
        public ?string $basketId,
    ) {}
}
