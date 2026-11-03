<?php

namespace Guixa\Ordering\Domain;

/** The read side of Sales that reception and closure need. */
interface WeeklyBasketView
{
    public function snapshot(string $cycleId): WeeklyBasketSnapshot;
}
