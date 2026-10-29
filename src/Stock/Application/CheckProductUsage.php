<?php

namespace Guixa\Stock\Application;

use Guixa\Stock\Domain\StockMovementRepository;

final class CheckProductUsage
{
    public function __construct(private readonly StockMovementRepository $movements) {}

    public function __invoke(string $productId): bool
    {
        return $this->movements->hasProductReference($productId);
    }
}
