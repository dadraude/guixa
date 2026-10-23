<?php

namespace Tests\Support\Contracts;

use Guixa\Ordering\Domain\CooperativeSettings;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;
use PHPUnit\Framework\Attributes\Test;

/** Behaviour every CooperativeSettings promises; see ProbeNoteRepositoryContract for how it is shared. */
trait CooperativeSettingsContract
{
    abstract protected function settings(): CooperativeSettings;

    #[Test]
    public function there_is_no_pickup_weekday_until_one_is_set(): void
    {
        $this->assertNull($this->settings()->pickupWeekday());
    }

    #[Test]
    public function it_remembers_a_changed_pickup_weekday(): void
    {
        $settings = $this->settings();

        $settings->changePickupWeekday(PickupWeekday::of(5));
        $settings->changePickupWeekday(PickupWeekday::of(2));

        $this->assertSame(2, $settings->pickupWeekday()?->isoDay);
    }
}
