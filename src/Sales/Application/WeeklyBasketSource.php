<?php

namespace Guixa\Sales\Application;

/** What Sales needs from Ordering to validate a weekly base: the source cycle lock, the closure attempt and where its source order belongs. */
interface WeeklyBasketSource extends WeeklySourceOrders
{
    /** Locks the source cycle until the surrounding transaction ends; the cycle must be settled. */
    public function lockSettled(string $cycleId): void;

    /** Closes the cycle when every weekly basket, base or late, is finished. */
    public function closeIfFinished(string $cycleId): void;
}
