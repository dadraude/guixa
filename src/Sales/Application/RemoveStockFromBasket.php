<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class RemoveStockFromBasket
{
    public function __construct(private readonly BasketRepository $baskets, private readonly TransactionRunner $transactions, private readonly Clock $clock) {}

    public function __invoke(Actor $actor, string $basketId, string $productId): void
    {
        Gate::forUser($actor)->authorize('manage-sales');
        $this->transactions->run(function () use ($basketId, $productId): void {
            $basket = $this->baskets->lock($basketId) ?? throw BasketNotFound::withId($basketId);
            if ($basket->state !== 'draft') {
                throw BasketNotEditable::create();
            }
            $lines = array_map(fn ($line) => $line->productId === $productId && $line->removedAt === null
                ? $line->removedAt($this->clock->now()) : $line, $basket->lines);
            $this->baskets->save($basket->withLines($lines));
        });
    }
}
