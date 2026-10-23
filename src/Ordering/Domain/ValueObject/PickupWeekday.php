<?php

namespace Guixa\Ordering\Domain\ValueObject;

use DateTimeInterface;
use Guixa\Ordering\Domain\Error\InvalidPickupWeekday;

/** The one day of the week every cycle is picked up on, as an ISO day: 1 Monday to 7 Sunday (R4c). */
final readonly class PickupWeekday
{
    private function __construct(public int $isoDay) {}

    public static function of(int $isoDay): self
    {
        if ($isoDay < 1 || $isoDay > 7) {
            throw InvalidPickupWeekday::of($isoDay);
        }

        return new self($isoDay);
    }

    public function includes(DateTimeInterface $date): bool
    {
        return (int) $date->format('N') === $this->isoDay;
    }
}
