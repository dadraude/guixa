<?php

namespace Guixa\Membership\Application;

final readonly class HouseholdData
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
    ) {}
}
