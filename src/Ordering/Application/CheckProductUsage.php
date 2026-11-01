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
        if ($stock) {
            return $this->orders->hasStockReference($productId);
        }

        return $this->orders->hasWeeklyReference($productId)
            || $this->receivedTotals->hasProductReference($productId);
    }
}
