<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\NoHousehold;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class ListMemberCycles
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly Clock $clock,
    ) {}

    /** @return list<MemberCycleData> by pickup date, earliest first */
    public function __invoke(Actor $actor): array
    {
        $householdId = $actor->householdId ?? throw NoHousehold::create();
        Gate::forUser($actor)->authorize('memberOf', ['household', $householdId]);

        $now = $this->clock->now()->setTimezone($this->clock->timezone());

        $lineCounts = $this->orders->lineCountsOfHousehold($householdId);

        return array_map(
            fn (Cycle $cycle) => new MemberCycleData(
                $cycle->id,
                $cycle->pickupDate()->format('Y-m-d'),
                $cycle->isOpenForMembers($now),
                $cycle->isCancelled(),
                $lineCounts[$cycle->id] ?? 0,
            ),
            $this->cycles->list(),
        );
    }
}
