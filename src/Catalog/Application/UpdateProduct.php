<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\ProducerCycles;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Money;
use Illuminate\Support\Facades\Gate;

final class UpdateProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProducerCycles $cycles,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(
        Actor $actor,
        string $id,
        string $name,
        string $unit,
        int $priceCents,
        bool $variable,
        ?string $containersPerUnit,
    ): void {
        $this->transactions->run(function () use ($actor, $id, $name, $unit, $priceCents, $variable, $containersPerUnit): void {
            $product = $this->products->getForUpdate($id) ?? throw ProductNotFound::withId($id);
            Gate::forUser($actor)->authorize('update', $product);
            $product->change($name, $unit, Money::ofCents($priceCents), $variable, $containersPerUnit, $this->cycles->hasUnsettledLines($id));
            $this->products->add($product);
        });
    }
}
