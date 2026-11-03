<?php

namespace Tests\Support\InMemory;

use Guixa\Ordering\Application\SalesBaskets;
use Guixa\Ordering\Application\WeeklySourceData;
use Guixa\Ordering\Domain\WeeklyBasketEntry;
use Guixa\Ordering\Domain\WeeklyBasketSnapshot;
use Guixa\Shared\Domain\Quantity;

/**
 * Sales' weekly baskets as Ordering sees them: one base per source order plus at most one open late draft. A draft base
 * follows its order on sync; `validate` freezes it. A late arrival goes to an unvalidated base, else to the open late draft.
 */
final class FakeSalesBaskets implements SalesBaskets
{
    /** @var array<string, WeeklySourceData> by source order id */
    public array $created = [];

    /** @var array<string, string> state by source order id */
    private array $states = [];

    /** @var array<string, array<string, int>> effective thousandths by order id and product id */
    private array $effective = [];

    /** @var array<string, list<array{state: string, effective: array<string, int>}>> late weekly baskets by source order id, oldest first */
    private array $lates = [];

    public function createWeeklyBase(WeeklySourceData $source): void
    {
        if (isset($this->created[$source->orderId])) {
            return;
        }

        $this->created[$source->orderId] = $source;
        $this->follow($source);
    }

    public function syncWeeklyBase(WeeklySourceData $source): void
    {
        if (! isset($this->created[$source->orderId])) {
            $this->createWeeklyBase($source);

            return;
        }

        if ($this->states[$source->orderId] === 'validated') {
            return;
        }

        $previous = $this->created[$source->orderId];
        $this->created[$source->orderId] = $source;
        $this->follow($source, $previous);
    }

    public function addLateArrival(WeeklySourceData $source, array $productIds): void
    {
        if (! isset($this->created[$source->orderId]) || ! in_array($this->states[$source->orderId], ['validated', 'collected'], true)) {
            $this->syncWeeklyBase($source);

            return;
        }

        $listed = [];
        foreach ($source->lines as $line) {
            if (in_array($line->productId, $productIds, true)) {
                $listed[$line->productId] = Quantity::fromString($line->allocated)->thousandths;
            }
        }

        foreach ($this->lates[$source->orderId] ?? [] as $index => $late) {
            if (in_array($late['state'], ['draft', 'nothing_to_collect'], true)) {
                $effective = array_replace($late['effective'], $listed);
                $this->lates[$source->orderId][$index] = ['state' => array_sum($effective) > 0 ? 'draft' : 'nothing_to_collect', 'effective' => $effective];

                return;
            }
        }

        $arrived = array_filter($listed, fn (int $thousandths) => $thousandths > 0);
        if ($arrived !== []) {
            $this->lates[$source->orderId][] = ['state' => 'draft', 'effective' => $arrived];
        }
    }

    public function pendingCycleIds(string $householdId): array
    {
        return array_values(array_unique(array_map(fn (WeeklySourceData $source) => $source->cycleId, array_filter($this->created,
            fn (WeeklySourceData $source) => $source->householdId === $householdId
                && (in_array($this->states[$source->orderId], ['draft', 'validated'], true)
                    || array_filter($this->lates[$source->orderId] ?? [], fn (array $late) => in_array($late['state'], ['draft', 'validated'], true)) !== [])))));
    }

    public function snapshot(string $cycleId): WeeklyBasketSnapshot
    {
        $entries = [];
        foreach ($this->created as $orderId => $source) {
            if ($source->cycleId !== $cycleId) {
                continue;
            }
            $entries[] = new WeeklyBasketEntry("base-{$orderId}", $source->householdId, $this->states[$orderId], false, $this->effective[$orderId]);
            foreach ($this->lates[$orderId] ?? [] as $index => $late) {
                $entries[] = new WeeklyBasketEntry("late-{$orderId}-{$index}", $source->householdId, $late['state'], true, $late['effective']);
            }
        }

        return new WeeklyBasketSnapshot($entries);
    }

    /** Staff records what the household really takes, like `SetBasketActualQuantity`. */
    public function setActual(string $orderId, string $productId, string $quantity): void
    {
        $this->effective[$orderId][$productId] = Quantity::fromString($quantity)->thousandths;
    }

    /** Staff validated the base: from now on it is frozen. */
    public function validate(string $orderId): void
    {
        $this->states[$orderId] = 'validated';
    }

    /** Staff validated the household's open late weekly basket. */
    public function validateLate(string $orderId): void
    {
        foreach ($this->lates[$orderId] ?? [] as $index => $late) {
            if ($late['state'] === 'draft') {
                $this->lates[$orderId][$index] = ['state' => 'validated', 'effective' => $late['effective']];
            }
        }
    }

    /** @return list<array{state: string, effective: array<string, int>}> */
    public function latesOfHousehold(string $cycleId, string $householdId): array
    {
        $base = $this->baseOfHousehold($cycleId, $householdId);

        return $base === null ? [] : ($this->lates[$base->orderId] ?? []);
    }

    /** The household took its goods: the validated base is no longer an obligation. */
    public function collect(string $orderId): void
    {
        $this->states[$orderId] = 'collected';
    }

    public function stateOfHousehold(string $cycleId, string $householdId): ?string
    {
        $source = $this->baseOfHousehold($cycleId, $householdId);

        return $source === null ? null : $this->states[$source->orderId];
    }

    public function baseOfHousehold(string $cycleId, string $householdId): ?WeeklySourceData
    {
        foreach ($this->created as $source) {
            if ($source->cycleId === $cycleId && $source->householdId === $householdId) {
                return $source;
            }
        }

        return null;
    }

    /** An allocation that changed resets the actual quantity of that line, like the real base does. */
    private function follow(WeeklySourceData $source, ?WeeklySourceData $previous = null): void
    {
        $before = [];
        foreach ($previous === null ? [] : $previous->lines as $line) {
            $before[$line->productId] = $line->allocated;
        }

        $actual = [];
        $positive = false;
        foreach ($source->lines as $line) {
            $thousandths = Quantity::fromString($line->allocated)->thousandths;
            $actual[$line->productId] = isset($before[$line->productId]) && $before[$line->productId] === $line->allocated
                ? ($this->effective[$source->orderId][$line->productId] ?? $thousandths) : $thousandths;
            $positive = $positive || $thousandths > 0;
        }
        $this->effective[$source->orderId] = $actual;
        $this->states[$source->orderId] = $positive ? 'draft' : 'nothing_to_collect';
    }
}
