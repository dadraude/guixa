<?php

namespace Guixa\Ordering\Application;

use Guixa\Shared\Application\Actor;

final class ListPendingPickups
{
    public function __construct(private readonly ReadPendingPickups $readPendingPickups) {}

    /** @return list<PendingPickupData> ordered by pickup date */
    public function __invoke(Actor $actor, string $householdId): array
    {
        $actor->assertManager();

        return ($this->readPendingPickups)($householdId);
    }
}
