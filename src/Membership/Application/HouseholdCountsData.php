<?php

namespace Guixa\Membership\Application;

final readonly class HouseholdCountsData
{
    public function __construct(public int $total, public int $active) {}
}
