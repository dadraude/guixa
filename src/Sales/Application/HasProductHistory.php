<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;

final class HasProductHistory
{
    public function __construct(private readonly BasketRepository $baskets) {}

    public function __invoke(string $productId): bool
    {
        return $this->baskets->hasProductHistory($productId);
    }
}
