<?php

namespace Guixa\Membership\Application;

use Guixa\Shared\Application\Actor;

/** The households a member can be moved to: every one but the household they are in. */
final class ListOtherHouseholds
{
    public function __construct(private readonly ListHouseholds $listHouseholds) {}

    /** @return list<HouseholdSummary> */
    public function __invoke(Actor $actor, string $householdId): array
    {
        return array_values(array_filter(
            ($this->listHouseholds)($actor),
            fn (HouseholdSummary $household) => $household->id !== $householdId,
        ));
    }
}
