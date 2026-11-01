<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\Error\ProductUsed;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Catalog\Domain\ProductUsage;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\Gate;

final class DeleteUnusedProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductUsage $usage,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $id): void
    {
        $this->transactions->run(function () use ($actor, $id): void {
            $product = $this->products->getForUpdate($id) ?? throw ProductNotFound::withId($id);
            Gate::forUser($actor)->authorize('delete', $product);
            if ($this->usage->hasAny($id)) {
                throw ProductUsed::create();
            }
            $this->products->delete($id);
        });
    }
}
