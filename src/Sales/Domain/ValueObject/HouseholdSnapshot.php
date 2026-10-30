<?php

namespace Guixa\Sales\Domain\ValueObject;

final readonly class HouseholdSnapshot
{
    public function __construct(public string $id, public string $name, public bool $active) {}
}
