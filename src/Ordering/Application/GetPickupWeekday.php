<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CooperativeSettings;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class GetPickupWeekday
{
    public function __construct(private readonly CooperativeSettings $settings) {}

    /** @return ?int 1 Monday to 7 Sunday, null until one is set (R4c) */
    public function __invoke(Actor $actor): ?int
    {
        Gate::forUser($actor)->authorize('manage');

        return $this->settings->pickupWeekday()?->isoDay;
    }
}
