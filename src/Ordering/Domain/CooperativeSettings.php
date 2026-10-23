<?php

namespace Guixa\Ordering\Domain;

use Guixa\Ordering\Domain\ValueObject\PickupWeekday;

/** The cooperative's single set of settings a manager changes in the app (D11). */
interface CooperativeSettings
{
    /** Null until a manager sets one (R4c). */
    public function pickupWeekday(): ?PickupWeekday;

    public function changePickupWeekday(PickupWeekday $weekday): void;
}
