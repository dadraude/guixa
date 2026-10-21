<?php

namespace Guixa\Shared\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Guixa\Shared\Domain\Clock;

final readonly class SystemClock implements Clock
{
    private DateTimeZone $timezone;

    public function __construct(string $timezone)
    {
        $this->timezone = new DateTimeZone($timezone);
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }
}
