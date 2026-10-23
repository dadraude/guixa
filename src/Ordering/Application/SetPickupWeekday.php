<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CooperativeSettings;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;
use Guixa\Shared\Application\Actor;

final class SetPickupWeekday
{
    public function __construct(private readonly CooperativeSettings $settings) {}

    /** @param  int  $isoWeekday  1 Monday to 7 Sunday; only cycles opened afterwards use it (R4c). */
    public function __invoke(Actor $actor, int $isoWeekday): void
    {
        $actor->assertManager();

        $this->settings->changePickupWeekday(PickupWeekday::of($isoWeekday));
    }
}
