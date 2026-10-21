<?php

namespace Guixa\Shared\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Guixa\Shared\Domain\Error\InvalidLocalTime;

final class LocalTime
{
    /**
     * Resolves a local date and HH:MM to one instant. A time skipped by daylight saving moves
     * forward by the gap (02:30 becomes 03:30); a repeated time means its first occurrence.
     * PHP resolves the first case itself but picks the second occurrence in the second case.
     */
    public static function at(DateTimeInterface $date, string $time, DateTimeZone $timezone): DateTimeImmutable
    {
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)\z/', $time)) {
            throw InvalidLocalTime::malformed($time);
        }

        $instant = new DateTimeImmutable($date->format('Y-m-d').' '.$time.':00', $timezone);

        return self::firstOccurrence($instant, $timezone);
    }

    private static function firstOccurrence(DateTimeImmutable $instant, DateTimeZone $timezone): DateTimeImmutable
    {
        $timestamp = $instant->getTimestamp();
        $transitions = $timezone->getTransitions($timestamp - 86400, $timestamp + 86400);
        $previousOffset = array_shift($transitions)['offset'] ?? null;

        foreach ($transitions as $transition) {
            $repeatedSeconds = $previousOffset - $transition['offset'];
            $previousOffset = $transition['offset'];
            $isRepeated = $repeatedSeconds > 0
                && $timestamp >= $transition['ts']
                && $timestamp < $transition['ts'] + $repeatedSeconds;

            if ($isRepeated) {
                return $instant->setTimestamp($timestamp - $repeatedSeconds);
            }
        }

        return $instant;
    }
}
