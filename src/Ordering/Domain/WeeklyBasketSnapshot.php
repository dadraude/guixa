<?php

namespace Guixa\Ordering\Domain;

/** What Sales holds for a cycle's weekly baskets: their states and the effective quantity of each household's products. */
final readonly class WeeklyBasketSnapshot
{
    /** @param list<WeeklyBasketEntry> $baskets */
    public function __construct(private array $baskets = []) {}

    /** The household has a validated weekly basket, base or late (R18, R23). */
    public function isValidated(string $householdId): bool
    {
        foreach ($this->baskets as $basket) {
            if ($basket->householdId === $householdId && $basket->isValidated()) {
                return true;
            }
        }

        return false;
    }

    /** The id of a validated weekly basket that has a line of the product, to point staff to its corrections (R18, S63). */
    public function validatedBasketWith(string $productId): ?string
    {
        foreach ($this->baskets as $basket) {
            if ($basket->isValidated() && array_key_exists($productId, $basket->effective)) {
                return $basket->basketId;
            }
        }

        return null;
    }

    /** What the household takes of the product across its weekly baskets; null when none has a line of it. */
    public function effective(string $householdId, string $productId): ?int
    {
        $total = null;
        foreach ($this->baskets as $basket) {
            if ($basket->householdId === $householdId && array_key_exists($productId, $basket->effective)) {
                $total = ($total ?? 0) + $basket->effective[$productId];
            }
        }

        return $total;
    }

    /** The household's base basket; a late one never stands in for it. */
    public function basketId(string $householdId): ?string
    {
        foreach ($this->baskets as $basket) {
            if ($basket->householdId === $householdId && ! $basket->late) {
                return $basket->basketId;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function states(): array
    {
        return array_map(fn (WeeklyBasketEntry $basket) => $basket->state, $this->baskets);
    }
}
