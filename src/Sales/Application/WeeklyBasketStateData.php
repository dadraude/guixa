<?php

namespace Guixa\Sales\Application;

/** A weekly basket as Ordering reads it: its household, state and effective quantity per product, in thousandths. */
final readonly class WeeklyBasketStateData
{
    /** @param array<string, int> $effective */
    public function __construct(public string $householdId, public string $state, public array $effective, public string $basketId = '', public bool $late = false) {}
}
