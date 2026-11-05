<?php

namespace Guixa\Sales\Application;

/** Where weekly source orders belong: a basket stores its source order id and no cycle of its own. */
interface WeeklySourceOrders
{
    /**
     * @param  list<string>  $sourceOrderIds
     * @return array<string, string> cycle id by source order id; unknown orders are left out
     */
    public function cyclesOf(array $sourceOrderIds): array;

    /** @return list<string> ids of every order of the cycle, cancelled ones included */
    public function ofCycle(string $cycleId): array;
}
