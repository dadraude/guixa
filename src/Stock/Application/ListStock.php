<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\StockProductRepository;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;
use Illuminate\Support\Facades\Gate;

final class ListStock
{
    public function __construct(
        private readonly StockProductRepository $products,
        private readonly StockMovementRepository $movements,
    ) {}

    /** @return list<StockLevelData> */
    public function __invoke(Actor $actor): array
    {
        Gate::forUser($actor)->authorize('manage-sales');

        $available = $this->movements->availableByProduct();

        return array_map(
            fn ($product) => new StockLevelData(
                $product->id,
                $product->name(),
                $product->unit(),
                $product->price()->cents,
                $product->isVariable(),
                $product->containersPerUnit()?->toString(),
                $product->isActive(),
                ($available[$product->id] ?? SignedQuantity::ofThousandths(0))->toString(),
                $product->producerId,
            ),
            array_values(array_filter(
                $this->products->list(),
                fn ($product) => $actor->hasRole(Role::Manager) || $product->isActive(),
            )),
        );
    }
}
