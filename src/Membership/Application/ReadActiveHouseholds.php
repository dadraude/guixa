<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\HouseholdRepository;

final class ReadActiveHouseholds
{
    public function __construct(private readonly HouseholdRepository $households) {}

    /** @return list<ActiveHouseholdData> */
    public function __invoke(): array
    {
        return array_map(fn ($household) => new ActiveHouseholdData($household->id, $household->name,
            $household->isActive()), $this->households->list());
    }
}
