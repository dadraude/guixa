<?php

namespace Guixa\Dashboard\Domain;

use Guixa\Dashboard\Domain\ValueObject\OrderWindow;
use Guixa\Dashboard\Domain\ValueObject\SentSummary;

/** Takes no actor: the caller has already checked the household, producer or role. */
interface OrderOpportunitiesRepository
{
    /** @return list<OrderWindow> the cycles members can order in now */
    public function openWindows(): array;

    /** @return list<SentSummary> only the given producer's own sent orders */
    public function sentSummaries(string $producerId): array;
}
