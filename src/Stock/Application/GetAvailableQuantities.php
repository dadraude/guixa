<?php

namespace Guixa\Stock\Application;

use Guixa\Stock\Domain\StockMovementRepository;

final class GetAvailableQuantities
{
    public function __construct(private readonly StockMovementRepository $movements) {}

    /** @param list<string> $productIds
     * @return array<string, string>
     */
    public function __invoke(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $available = $this->movements->availableByProduct();
        $result = [];
        foreach ($productIds as $id) {
            $result[$id] = ($available[$id] ?? null)?->toString() ?? '0.000';
        }

        return $result;
    }
}
