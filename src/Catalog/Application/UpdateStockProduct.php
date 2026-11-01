<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProductHasStockUse;
use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Catalog\Domain\ProductUsage;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Money;
use Illuminate\Support\Facades\Gate;

final class UpdateStockProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductUsage $usage,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $producerId, string $id, string $name, string $unit, int $priceCents, bool $variable, ?string $containersPerUnit): void
    {
        Gate::forUser($actor)->authorize('manage');

        $this->transactions->run(function () use ($producerId, $id, $name, $unit, $priceCents, $variable, $containersPerUnit): void {
            $product = $this->products->getForUpdate($id);
            if ($product === null || ! $product->isStock() || $product->producerId !== $producerId) {
                throw ProductNotFound::withId($id);
            }
            if ((trim($unit) !== $product->unit() || $variable !== $product->isVariable()) && $this->usage->hasStockUse($id)) {
                throw ProductHasStockUse::create();
            }
            $product->change($name, $unit, Money::ofCents($priceCents), $variable, $containersPerUnit);
            $this->products->add($product);
        });
    }
}
