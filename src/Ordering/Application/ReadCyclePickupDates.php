<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;

/** Internal query for Sales: the pickup date of each cycle a weekly basket was attributed to (R35a); it takes no actor. */
final class ReadCyclePickupDates
{
    public function __construct(private readonly CycleRepository $cycles) {}

    /**
     * @param  list<string>  $cycleIds
     * @return array<string, string> Y-m-d pickup date by cycle id; unknown cycles are left out
     */
    public function __invoke(array $cycleIds): array
    {
        $dates = [];

        foreach (array_unique($cycleIds) as $cycleId) {
            $cycle = $this->cycles->get($cycleId);

            if ($cycle !== null) {
                $dates[$cycleId] = $cycle->pickupDate()->format('Y-m-d');
            }
        }

        return $dates;
    }
}
