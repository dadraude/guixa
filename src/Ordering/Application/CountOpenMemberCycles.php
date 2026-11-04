<?php

namespace Guixa\Ordering\Application;

use Guixa\Shared\Application\Actor;

final class CountOpenMemberCycles
{
    public function __construct(private readonly ListMemberCycles $listMemberCycles) {}

    public function __invoke(Actor $actor): int
    {
        return count(array_filter(
            ($this->listMemberCycles)($actor),
            fn (MemberCycleData $cycle) => $cycle->open && ! $cycle->cancelled,
        ));
    }
}
