<?php

namespace Guixa\Sales\Application;

use DomainException;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Sales\Domain\Error\InvalidBasketQuantity;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Support\Facades\Gate;

/** Staff records what a household really takes of a weekly product before validation; the allocation and order stay untouched (R19, S14). */
final class SetBasketActualQuantity
{
    public function __construct(private readonly BasketRepository $baskets, private readonly WeeklyBasketSource $weeklySource,
        private readonly TransactionRunner $transactions) {}

    /** @param  string  $quantity  zero or more, at most 3 decimals; whole units for a non-variable product */
    public function __invoke(Actor $actor, string $basketId, string $productId, string $quantity): void
    {
        Gate::forUser($actor)->authorize('manage-sales');
        try {
            $actual = Quantity::fromString($quantity);
        } catch (DomainException) {
            throw InvalidBasketQuantity::create();
        }

        $this->transactions->run(function () use ($basketId, $productId, $actual): void {
            $peek = $this->baskets->get($basketId) ?? throw BasketNotFound::withId($basketId);
            if (! $peek->isWeekly()) {
                throw BasketNotEditable::create();
            }
            $this->weeklySource->lockSettled((string) $peek->referenceCycleId);
            $basket = $this->baskets->lock($basketId) ?? throw BasketNotFound::withId($basketId);
            if ($basket->state !== 'draft') {
                throw BasketNotEditable::create();
            }
            $line = $basket->activeLineFor($productId);
            if ($line === null || $line->kind !== 'weekly' || (! $line->variable && ! $actual->isWhole())) {
                throw InvalidBasketQuantity::create();
            }

            $this->baskets->save($basket->withActualQuantity($productId, $actual));
        });
    }
}
