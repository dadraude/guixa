<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ReceivedTotalRepository;

final class CheckProductUsage
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ReceivedTotalRepository $receivedTotals,
    ) {}

    public function __invoke(string $productId, bool $stock): bool
    {
        // Ordering holds weekly demand only; stock products are referenced by Sales baskets and Stock movements.
        if ($stock) {
            return false;
        }

        return $this->orders->hasWeeklyReference($productId)
            || $this->receivedTotals->hasProductReference($productId);
    }
}
