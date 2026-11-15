<?php

namespace Guixa\Ordering\Domain;

/** One weekly basket of a household in a cycle, base or late, as Sales reports it. */
final readonly class WeeklyBasketEntry
{
    /** @param array<string, int> $effective thousandths by product id, for every product the basket has a weekly line of */
    public function __construct(
        public string $basketId,
        public string $householdId,
        public string $state,
        public bool $late,
        public array $effective,
    ) {}

    /** A validated basket is financially frozen even when nobody collected it yet (R18, R23). */
    public function isValidated(): bool
    {
        return in_array($this->state, ['validated', 'collected'], true);
    }
}
