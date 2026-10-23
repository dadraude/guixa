<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Household;
use Guixa\Membership\Domain\HouseholdRepository;

/** Internal query for Ordering: the name and active status of households (R13, R16, R19). */
final class GetHouseholds
{
    public function __construct(private readonly HouseholdRepository $households) {}

    /**
     * @param  list<string>  $householdIds
     * @return array<string, HouseholdData> by household id; unknown households are left out
     */
    public function __invoke(array $householdIds): array
    {
        return array_map(
            fn (Household $household) => new HouseholdData($household->id, $household->name, $household->isActive()),
            $this->households->getMany(array_values(array_unique($householdIds))),
        );
    }
}
