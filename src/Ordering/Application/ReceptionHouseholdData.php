<?php

namespace Guixa\Ordering\Application;

final readonly class ReceptionHouseholdData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
