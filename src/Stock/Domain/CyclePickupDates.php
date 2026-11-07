<?php

namespace Guixa\Stock\Domain;

/** Stock's read port for the pickup date of the cycle a movement was attributed to. */
interface CyclePickupDates
{
    /**
     * @param  list<string>  $cycleIds
     * @return array<string, string> Y-m-d pickup date by cycle id; unknown cycles are left out
     */
    public function forCycles(array $cycleIds): array;
}
