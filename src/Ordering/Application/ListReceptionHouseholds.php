<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\ReceptionHouseholds;
use Guixa\Ordering\Domain\ValueObject\HouseholdInfo;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** The households a receiver can give a share to: the active ones by name, since an inactive one is refused (R16). */
final class ListReceptionHouseholds
{
    public function __construct(private readonly ReceptionHouseholds $households) {}

    /** @return list<ReceptionHouseholdData> */
    public function __invoke(Actor $actor): array
    {
        Gate::forUser($actor)->authorize('receive');

        return array_map(fn (HouseholdInfo $household) => new ReceptionHouseholdData($household->id, $household->name), $this->households->active());
    }
}
