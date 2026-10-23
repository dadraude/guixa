<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\OrderRepository;

/** Internal query for Catalog, which locks a product's unit and variable flag while this is true (R1, S5); it takes no actor. */
final class HasUnsettledLines
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function __invoke(string $productId): bool
    {
        return $this->orders->hasUnsettledLinesOf($productId);
    }
}
