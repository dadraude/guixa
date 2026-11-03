<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Households;
use Guixa\Ordering\Domain\ReceptionLine;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Ordering\Domain\ReceptionProduct;
use Guixa\Ordering\Domain\ValueObject\OrderableProduct;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Support\Facades\Gate;

/** What a manager sees of a producer's reception: per product the totals, the difference and every household's allocation (R14, R17). */
final class GetReception
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly ReceptionLoader $loader,
        private readonly Households $households,
    ) {}

    public function __invoke(Actor $actor, string $cycleId, string $producerId): ReceptionData
    {
        Gate::forUser($actor)->authorize('manage');

        $cycle = $this->cycles->get($cycleId) ?? throw CycleNotFound::withId($cycleId);
        $loaded = $this->loader->load($cycle, $producerId);
        $names = $this->householdNames($loaded->reception->products);
        $shown = array_flip(array_map(fn (ReceptionProduct $product) => $product->productId, $loaded->reception->products));

        return new ReceptionData(
            $cycle->id,
            $producerId,
            $cycle->pickupDate()->format('Y-m-d'),
            array_map(fn (ReceptionProduct $product) => $this->productData($product, $names), $loaded->reception->products),
            array_values(array_map(
                fn (OrderableProduct $product) => new ReceptionOptionData($product->id, $product->name, $product->unit, $product->variable),
                array_filter($loaded->catalog, fn (OrderableProduct $product) => ! isset($shown[$product->id])),
            )),
            $cycle->isValidated($producerId),
            $cycle->isDistributed($producerId),
            $cycle->isSettled(),
            $cycle->isClosed(),
        );
    }

    /**
     * @param  list<ReceptionProduct>  $products
     * @return array<string, string> household name by id; a household Membership does not know shows its id
     */
    private function householdNames(array $products): array
    {
        $ids = [];

        foreach ($products as $product) {
            foreach ($product->lines as $line) {
                $ids[$line->householdId] = true;
            }
        }

        $known = $this->households->find(array_keys($ids));

        return array_map(fn (string $id) => isset($known[$id]) ? $known[$id]->name : $id, array_combine(array_keys($ids), array_keys($ids)));
    }

    /** @param  array<string, string>  $names */
    private function productData(ReceptionProduct $product, array $names): ReceptionProductData
    {
        $allocations = array_map(fn (ReceptionLine $line) => new ReceptionAllocationData(
            $line->householdId,
            $names[$line->householdId],
            $line->ordered->toString(),
            $line->allocated->toString(),
            $line->allocatedByHand,
            $line->delivered->toString(),
            $line->isAdjusted(),
        ), $product->lines);
        usort($allocations, fn (ReceptionAllocationData $a, ReceptionAllocationData $b) => [$a->householdName, $a->householdId] <=> [$b->householdName, $b->householdId]);

        return new ReceptionProductData(
            $product->productId,
            $product->name,
            $product->unit,
            $product->variable,
            $product->nobodyOrdered(),
            $product->ordered()->toString(),
            $product->received->toString(),
            $product->allocated()->toString(),
            $product->delivered()->toString(),
            $this->signed($product->differenceThousandths()),
            $product->isBalanced(),
            $product->balancedForDistribution(),
            $product->manuallyAllocatedHouseholds() !== [],
            $product->hasAdjustments(),
            $allocations,
        );
    }

    private function signed(int $thousandths): string
    {
        $amount = Quantity::ofThousandths(abs($thousandths))->toString();

        return $thousandths < 0 ? "-{$amount}" : $amount;
    }
}
