<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\StockOfferCatalog;
use Guixa\Sales\Domain\StockQuantities;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;

final class ListStockOffers
{
    public function __construct(
        private readonly StockOfferCatalog $catalog,
        private readonly StockQuantities $quantities,
    ) {}

    /** @return list<StockOfferData> */
    public function __invoke(Actor $actor): array
    {
        if (! $actor->hasRole(Role::Member) && ! $actor->hasRole(Role::Manager)) {
            $actor->assertMemberOf($actor->householdId ?? '');
        }

        $products = $this->catalog->active();
        if ($products === []) {
            return [];
        }

        $available = $this->quantities->forProducts(array_map(fn ($product) => $product->id, $products));

        return array_map(static function ($product) use ($available): StockOfferData {
            $quantity = $available[$product->id] ?? '0.000';

            return new StockOfferData(
                $product->id,
                $product->producerId,
                $product->producerName,
                $product->name,
                $product->unit,
                $product->priceCents,
                $quantity,
                (float) $quantity <= 0,
            );
        }, $products);
    }
}
