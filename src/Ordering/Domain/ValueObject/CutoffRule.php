<?php

namespace Guixa\Ordering\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Guixa\Shared\Domain\LocalTime;

/**
 * A producer's default cutoff as Ordering needs it: days before the pickup date, at a local time (R5).
 * Catalog validates it; Ordering only reads what Catalog hands over.
 */
final readonly class CutoffRule
{
    private function __construct(
        public int $daysBefore,
        public string $time,
    ) {}

    public static function of(int $daysBefore, string $time): self
    {
        return new self($daysBefore, $time);
    }

    /** The instant the rule closes orders for a cycle picked up on `$pickupDate` (R5, S38). */
    public function closesAt(DateTimeInterface $pickupDate, DateTimeZone $timezone): DateTimeImmutable
    {
        // Calendar arithmetic on a bare date, so no daylight-saving change can shift the day.
        $day = new DateTimeImmutable($pickupDate->format('Y-m-d'), new DateTimeZone('UTC'));

        return LocalTime::at($day->modify("-{$this->daysBefore} days"), $this->time, $timezone);
    }
}
