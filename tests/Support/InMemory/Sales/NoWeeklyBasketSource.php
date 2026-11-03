<?php

namespace Tests\Support\InMemory\Sales;

use Guixa\Sales\Application\WeeklyBasketSource;

/** For independent baskets, which never touch a weekly cycle. */
final class NoWeeklyBasketSource implements WeeklyBasketSource
{
    public function lockSettled(string $cycleId): void {}

    public function closeIfFinished(string $cycleId): void {}
}
