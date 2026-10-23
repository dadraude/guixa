<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;

final class ListCycles
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly Clock $clock,
    ) {}

    /** @return list<CycleData> by pickup date, earliest first */
    public function __invoke(Actor $actor): array
    {
        $actor->assertManager();

        $timezone = $this->clock->timezone();
        $now = $this->clock->now()->setTimezone($timezone);

        return array_map(
            fn (Cycle $cycle) => CycleData::of($cycle, $now, $timezone),
            $this->cycles->list(),
        );
    }
}
