<?php

namespace Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Guixa\Shared\Domain\Clock;

final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    private DateTimeZone $timezone;

    public function __construct(string $now, string $timezone = 'Europe/Madrid')
    {
        $this->timezone = new DateTimeZone($timezone);
        $this->now = new DateTimeImmutable($now, $this->timezone);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    public function travelTo(string $now): void
    {
        $this->now = new DateTimeImmutable($now, $this->timezone);
    }
}
