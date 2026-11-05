<?php

namespace Guixa\Ordering\Application;

use DateTimeImmutable;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class ListCycles
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly Clock $clock,
        private readonly SendableTotals $totals,
    ) {}

    /** @return list<CycleData> by pickup date, earliest first */
    public function __invoke(Actor $actor): array
    {
        Gate::forUser($actor)->authorize('manage');

        $timezone = $this->clock->timezone();
        $now = $this->clock->now()->setTimezone($timezone);

        return array_map(
            fn (Cycle $cycle) => CycleData::of($cycle, $now, $timezone, $this->manuallySendable($cycle, $now)),
            $this->cycles->list(),
        );
    }

    /** @return list<string> */
    private function manuallySendable(Cycle $cycle, DateTimeImmutable $now): array
    {
        return ($this->totals)($cycle, $cycle->producersReadyToSend($now));
    }
}
