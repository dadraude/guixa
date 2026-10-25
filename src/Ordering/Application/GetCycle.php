<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;

final class GetCycle
{
    public function __construct(private readonly CycleRepository $cycles, private readonly Clock $clock) {}

    public function __invoke(Actor $actor, string $cycleId): CycleData
    {
        $actor->assertManager();
        $cycle = $this->cycles->get($cycleId) ?? throw CycleNotFound::withId($cycleId);

        return CycleData::of($cycle, $this->clock->now(), $this->clock->timezone());
    }
}
