<?php

namespace Guixa\Ordering\Application;

final readonly class CycleCountsData
{
    public function __construct(public int $total, public int $active) {}
}
