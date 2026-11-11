<?php

namespace Guixa\Dashboard\Application;

/** The existing screen a group leads to: the one record's detail when it is the only one, otherwise the area's list; for baskets staff-wide or the member's own. */
final readonly class DestinationData
{
    public function __construct(
        public bool $staffWide,
        public ?string $basketId,
        public DestinationArea $area = DestinationArea::Baskets,
        public ?string $producerId = null,
        /** Whether the staff list is narrowed to pending collections; drafts are in the unfiltered list. */
        public bool $collectionList = true,
    ) {}
}
