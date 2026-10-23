<?php

namespace Tests\Support\InMemory;

use Guixa\Ordering\Domain\CooperativeSettings;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;

final class InMemoryCooperativeSettings implements CooperativeSettings
{
    private ?PickupWeekday $pickupWeekday = null;

    public function pickupWeekday(): ?PickupWeekday
    {
        return $this->pickupWeekday;
    }

    public function changePickupWeekday(PickupWeekday $weekday): void
    {
        $this->pickupWeekday = $weekday;
    }
}
