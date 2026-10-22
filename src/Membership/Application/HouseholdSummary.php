<?php

namespace Guixa\Membership\Application;

final readonly class HouseholdSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
        public int $memberCount,
    ) {}
}
