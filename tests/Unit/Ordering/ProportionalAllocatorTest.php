<?php

use Guixa\Ordering\Domain\Error\NobodyOrderedProduct;
use Guixa\Ordering\Domain\ProportionalAllocator;
use Guixa\Ordering\Domain\ValueObject\ProportionalClaim;
use Guixa\Shared\Domain\Quantity;

function claim(string $household, string $ordered, string $createdAt = '2026-01-01 10:00:00', ?string $lineId = null): ProportionalClaim
{
    return new ProportionalClaim($household, Quantity::fromString($ordered), new DateTimeImmutable($createdAt), $lineId ?? "line-{$household}");
}

/** @param array<string, Quantity> $allocation */
function allocationStrings(array $allocation): array
{
    return array_map(fn (Quantity $quantity) => $quantity->toString(), $allocation);
}

const WHOLE = '1';
const GRAM = '0.001';

it('S10 splits a shortage and gives the leftover unit to the earlier line on a tie', function () {
    $result = (new ProportionalAllocator)->allocate(
        Quantity::fromString('15'),
        Quantity::fromString(WHOLE),
        [claim('A', '4'), claim('B', '6', '2026-01-01 10:00:01'), claim('C', '10', '2026-01-01 10:00:02')],
    );

    expect(allocationStrings($result))->toBe(['A' => '3.000', 'B' => '5.000', 'C' => '7.000']);
});

it('S11 splits a surplus of a variable product evenly', function () {
    $result = (new ProportionalAllocator)->allocate(
        Quantity::fromString('5'),
        Quantity::fromString(GRAM),
        [claim('A', '2'), claim('B', '2')],
    );

    expect(allocationStrings($result))->toBe(['A' => '2.500', 'B' => '2.500']);
});

it('S12 hands leftover grams to the earliest lines', function () {
    $result = (new ProportionalAllocator)->allocate(
        Quantity::fromString('1'),
        Quantity::fromString(GRAM),
        [claim('A', '1', '2026-01-01 10:00:00'), claim('B', '1', '2026-01-01 10:00:01'), claim('C', '1', '2026-01-01 10:00:02')],
    );

    expect(allocationStrings($result))->toBe(['A' => '0.334', 'B' => '0.333', 'C' => '0.333']);
});

it('S31 gives the leftover unit to the largest remainder', function () {
    $result = (new ProportionalAllocator)->allocate(
        Quantity::fromString('14'),
        Quantity::fromString(WHOLE),
        [claim('A', '4'), claim('B', '6'), claim('C', '10')],
    );

    expect(allocationStrings($result))->toBe(['A' => '3.000', 'B' => '4.000', 'C' => '7.000']);
});

it('breaks a creation-time tie by line id', function () {
    $result = (new ProportionalAllocator)->allocate(
        Quantity::fromString('1'),
        Quantity::fromString(WHOLE),
        [claim('A', '1', lineId: '0002'), claim('B', '1', lineId: '0001')],
    );

    expect(allocationStrings($result))->toBe(['A' => '0.000', 'B' => '1.000']);
});

it('allocates a zero target as zero for everyone', function () {
    $result = (new ProportionalAllocator)->allocate(
        Quantity::fromString('0'),
        Quantity::fromString(WHOLE),
        [claim('A', '4'), claim('B', '6')],
    );

    expect(allocationStrings($result))->toBe(['A' => '0.000', 'B' => '0.000']);
});

it('refuses to allocate when nobody ordered the product', function () {
    expect(fn () => (new ProportionalAllocator)->allocate(Quantity::fromString('3'), Quantity::fromString(WHOLE), []))
        ->toThrow(NobodyOrderedProduct::class);
});

it('keeps the sum exact, on the step, and within one step of the exact share', function () {
    mt_srand(155);
    $allocator = new ProportionalAllocator;

    foreach (range(1, 500) as $round) {
        $stepThousandths = mt_rand(0, 1) === 0 ? 1000 : 1;
        $households = mt_rand(1, 8);
        $claims = [];
        foreach (range(1, $households) as $index) {
            $claims[] = new ProportionalClaim(
                "H{$index}",
                Quantity::ofThousandths(mt_rand(1, 30) * $stepThousandths),
                new DateTimeImmutable('2026-01-01 10:00:00')->modify('+'.mt_rand(0, 3).' seconds'),
                sprintf('line-%02d', $index),
            );
        }
        $targetThousandths = mt_rand(0, 200) * $stepThousandths;

        $result = $allocator->allocate(Quantity::ofThousandths($targetThousandths), Quantity::ofThousandths($stepThousandths), $claims);

        $orderedTotal = array_sum(array_map(fn (ProportionalClaim $c) => $c->ordered->thousandths, $claims));
        $allocatedTotal = array_sum(array_map(fn (Quantity $q) => $q->thousandths, $result));
        expect($allocatedTotal)->toBe($targetThousandths);

        foreach ($claims as $claim) {
            $allocated = $result[$claim->householdId]->thousandths;
            expect($allocated % $stepThousandths)->toBe(0);
            $exactTimesTotal = $targetThousandths * $claim->ordered->thousandths;
            expect(($allocated - $stepThousandths) * $orderedTotal)->toBeLessThan($exactTimesTotal);
            expect(($allocated + $stepThousandths) * $orderedTotal)->toBeGreaterThan($exactTimesTotal);
        }
    }
});
