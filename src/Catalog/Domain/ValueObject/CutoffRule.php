<?php

namespace Guixa\Catalog\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Guixa\Catalog\Domain\Error\InvalidCutoffRule;
use Guixa\Shared\Domain\LocalTime;

/** When a producer's orders close, relative to the pickup date: 1 to 6 days before, at a local time (R5). */
final readonly class CutoffRule
{
    private const MIN_DAYS = 1;

    private const MAX_DAYS = 6;

    private function __construct(
        public int $daysBefore,
        public string $time,
    ) {}

    public static function of(int $daysBefore, string $time): self
    {
        if ($daysBefore < self::MIN_DAYS || $daysBefore > self::MAX_DAYS) {
            throw InvalidCutoffRule::daysOutOfRange($daysBefore);
        }

        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d\z/', $time)) {
            throw InvalidCutoffRule::malformedTime($time);
        }

        return new self($daysBefore, $time);
    }

    /** The instant the rule closes orders for a cycle picked up on `$pickupDate` (R5, S6, S38). */
    public function closesAt(DateTimeInterface $pickupDate, DateTimeZone $timezone): DateTimeImmutable
    {
        // Calendar arithmetic on a bare date, so no daylight-saving change can shift the day.
        $day = new DateTimeImmutable($pickupDate->format('Y-m-d'), new DateTimeZone('UTC'));

        return LocalTime::at($day->modify("-{$this->daysBefore} days"), $this->time, $timezone);
    }

    public function equals(self $other): bool
    {
        return $this->daysBefore === $other->daysBefore && $this->time === $other->time;
    }
}
