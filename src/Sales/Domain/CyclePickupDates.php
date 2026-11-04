<?php

namespace Guixa\Sales\Domain;

interface CyclePickupDates
{
    /**
     * @param  list<string>  $cycleIds
     * @return array<string, string> Y-m-d pickup date by cycle id; unknown cycles are left out
     */
    public function forCycles(array $cycleIds): array;
}
