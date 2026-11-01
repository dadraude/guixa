<?php

namespace Guixa\Ordering\Application;

use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class ListPendingPickups
{
    public function __construct(private readonly ReadPendingPickups $readPendingPickups) {}

    /** @return list<PendingPickupData> ordered by pickup date */
    public function __invoke(Actor $actor, string $householdId): array
    {
        Gate::forUser($actor)->authorize('manage');

        return ($this->readPendingPickups)($householdId);
    }
}
