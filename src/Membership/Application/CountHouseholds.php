<?php

namespace Guixa\Membership\Application;

use Guixa\Shared\Application\Actor;

final class CountHouseholds
{
    public function __construct(private readonly ListHouseholds $listHouseholds) {}

    public function __invoke(Actor $actor): HouseholdCountsData
    {
        $households = ($this->listHouseholds)($actor);

        return new HouseholdCountsData(
            count($households),
            count(array_filter($households, fn (HouseholdSummary $household) => $household->active)),
        );
    }
}
