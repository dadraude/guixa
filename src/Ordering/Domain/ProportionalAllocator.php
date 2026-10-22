<?php

namespace Guixa\Ordering\Domain;

use Guixa\Ordering\Domain\Error\NobodyOrderedProduct;
use Guixa\Ordering\Domain\ValueObject\ProportionalClaim;
use Guixa\Shared\Domain\Quantity;
use InvalidArgumentException;

/**
 * Splits a received total among households in proportion to what they ordered (R15).
 * Integer arithmetic only, counted in steps, so the allocations always add up to the target.
 */
final class ProportionalAllocator
{
    /**
     * @param  list<ProportionalClaim>  $claims
     * @return array<string, Quantity> allocation by household id
     */
    public function allocate(Quantity $target, Quantity $step, array $claims): array
    {
        $orderedSteps = $this->orderedInSteps($claims, $step);
        $totalOrdered = array_sum($orderedSteps);
        if ($claims === [] || $totalOrdered === 0) {
            throw NobodyOrderedProduct::create();
        }

        $targetSteps = $this->inSteps($target, $step);
        $wholeShare = intdiv($targetSteps, $totalOrdered);
        $restOfTarget = $targetSteps % $totalOrdered;

        $floors = [];
        $remainders = [];
        foreach ($orderedSteps as $index => $ordered) {
            $floors[$index] = $wholeShare * $ordered + intdiv($restOfTarget * $ordered, $totalOrdered);
            $remainders[$index] = ($restOfTarget * $ordered) % $totalOrdered;
        }

        $leftover = $targetSteps - array_sum($floors);
        foreach (array_slice($this->indexesByPriority($claims, $remainders), 0, $leftover) as $index) {
            $floors[$index]++;
        }

        $allocation = [];
        foreach ($claims as $index => $claim) {
            $current = $allocation[$claim->householdId]->thousandths ?? 0;
            $allocation[$claim->householdId] = Quantity::ofThousandths($current + $floors[$index] * $step->thousandths);
        }

        return $allocation;
    }

    /**
     * @param  list<ProportionalClaim>  $claims
     * @return list<int>
     */
    private function orderedInSteps(array $claims, Quantity $step): array
    {
        return array_map(fn (ProportionalClaim $claim) => $this->inSteps($claim->ordered, $step), $claims);
    }

    private function inSteps(Quantity $quantity, Quantity $step): int
    {
        if ($quantity->thousandths % $step->thousandths !== 0) {
            throw new InvalidArgumentException('Quantities must be multiples of the step.');
        }

        return intdiv($quantity->thousandths, $step->thousandths);
    }

    /**
     * Largest remainder first; ties by creation moment, then by line id (record order).
     *
     * @param  list<ProportionalClaim>  $claims
     * @param  array<int, int>  $remainders
     * @return list<int>
     */
    private function indexesByPriority(array $claims, array $remainders): array
    {
        $indexes = array_keys($claims);
        usort($indexes, fn (int $a, int $b) => [$remainders[$b], $claims[$a]->createdAt, $claims[$a]->lineId]
            <=> [$remainders[$a], $claims[$b]->createdAt, $claims[$b]->lineId]);

        return $indexes;
    }
}
