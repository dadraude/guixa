<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\Error\ProductUsed;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Catalog\Domain\ProductUsage;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

final class ChangeProductClassification
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductUsage $usage,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $id, bool $stock): void
    {
        $actor->assertManager();

        $this->transactions->run(function () use ($id, $stock): void {
            $product = $this->products->getForUpdate($id) ?? throw ProductNotFound::withId($id);
            if ($this->usage->hasAny($id)) {
                throw ProductUsed::create();
            }
            if ($product->isStock() !== $stock) {
                $this->products->setClassification($id, $stock);
            }
        });
    }
}
