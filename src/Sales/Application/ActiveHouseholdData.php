<?php

namespace Guixa\Sales\Application;

final readonly class ActiveHouseholdData
{
    public function __construct(public string $id, public string $name) {}
}
