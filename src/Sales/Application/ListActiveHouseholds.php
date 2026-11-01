<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\HouseholdDirectory;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class ListActiveHouseholds
{
    public function __construct(private readonly HouseholdDirectory $households) {}

    /** @return list<ActiveHouseholdData> */
    public function __invoke(Actor $actor): array
    {
        Gate::forUser($actor)->authorize('manage-sales');

        return array_map(
            fn ($household) => new ActiveHouseholdData($household->id, $household->name),
            array_values(array_filter($this->households->all(), fn ($household) => $household->active)),
        );
    }
}
