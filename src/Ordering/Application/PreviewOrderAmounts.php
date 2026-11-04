<?php

namespace Guixa\Ordering\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\InvalidQuantity;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;

/** Prices the quantities a member is typing, before they are saved. Changes nothing. */
final class PreviewOrderAmounts
{
    public function __construct(private readonly GetHouseholdOrder $getHouseholdOrder) {}

    /** @param  array<string, string>  $typed  quantity text by product id; products not listed keep their saved quantity */
    public function __invoke(Actor $actor, string $cycleId, array $typed): OrderPreviewData
    {
        $order = ($this->getHouseholdOrder)($actor, $cycleId);

        $prices = [];
        foreach ($order->producers as $producer) {
            foreach ($producer->products as $product) {
                $prices[$product->id] = $product->priceCents;
            }
        }

        $saved = [];
        $notDelivered = [];
        foreach ($order->lines as $line) {
            // A line keeps the price it had when it was last changed (R10).
            $prices[$line->productId] = $line->unitPriceCents;
            $saved[$line->productId] = $line->quantity;
            $notDelivered[$line->productId] = $line->notDelivered;
        }

        $amounts = [];
        foreach ($prices as $productId => $priceCents) {
            $amounts[$productId] = ($notDelivered[$productId] ?? false)
                ? 0
                : $this->amountCents($priceCents, $typed[$productId] ?? $saved[$productId] ?? '0');
        }

        return new OrderPreviewData(array_sum($amounts), $amounts);
    }

    /** A text that is not a quantity counts as nothing, as the save would refuse it. */
    private function amountCents(int $priceCents, string $quantity): int
    {
        try {
            return Money::ofCents($priceCents)->multiply(Quantity::fromString($quantity))->cents;
        } catch (InvalidQuantity) {
            return 0;
        }
    }
}
