<?php

namespace Guixa\Membership\Application;

use Guixa\Shared\Application\Actor;

final class ListActiveHouseholds
{
    public function __construct(private readonly ListHouseholds $listHouseholds) {}

    /** @return list<HouseholdSummary> */
    public function __invoke(Actor $actor): array
    {
        return array_values(array_filter(
            ($this->listHouseholds)($actor),
            fn (HouseholdSummary $household) => $household->active,
        ));
    }
}
