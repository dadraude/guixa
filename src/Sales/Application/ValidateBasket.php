<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketAlreadyValidated;
use Guixa\Sales\Domain\Error\BasketHasNoContents;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Sales\Domain\Error\StockProductUnavailable;
use Guixa\Sales\Domain\StockProducts;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class ValidateBasket
{
    public function __construct(private readonly BasketRepository $baskets, private readonly StockProducts $products,
        private readonly StockSeller $stock, private readonly WalletLedger $wallet,
        private readonly WeeklyBasketSource $weeklySource,
        private readonly TransactionRunner $transactions, private readonly Clock $clock) {}

    public function __invoke(Actor $actor, string $basketId): void
    {
        Gate::forUser($actor)->authorize('manage-sales');
        $this->transactions->run(function () use ($actor, $basketId): void {
            $source = $this->baskets->get($basketId) ?? throw BasketNotFound::withId($basketId);
            if ($source->referenceCycleId !== null && $source->kind === 'weekly') {
                // The cycle lock comes before the basket lock, like every other weekly mutation.
                $this->weeklySource->lockSettled($source->referenceCycleId);
            }
            $basket = $this->baskets->lock($basketId) ?? throw BasketNotFound::withId($basketId);
            if ($basket->state === 'validated' || $basket->state === 'collected') {
                throw BasketAlreadyValidated::create();
            }
            if ($basket->state !== 'draft') {
                throw BasketNotEditable::create();
            }
            $lines = $basket->activeLines();
            if ($lines === []) {
                throw BasketHasNoContents::create();
            }

            $stockLines = array_values(array_filter($lines, fn ($line) => $line->kind === 'stock'));
            usort($stockLines, fn ($left, $right) => strcmp($left->productId, $right->productId));
            foreach ($stockLines as $line) {
                // Lock every product identity in a stable order before any sale.
                if ($this->products->lock($line->productId) === null) {
                    throw StockProductUnavailable::create();
                }
            }
            foreach ($stockLines as $line) {
                $this->stock->sell($actor, $line->productId, $line->quantity->toString(), $basketId);
            }
            $validated = $basket->validated($actor->accountId, $this->clock->now());
            if ($validated->validatedTotalCents !== 0) {
                $this->wallet->charge($actor, $basket->householdId, $basketId, $validated->validatedTotalCents);
            }
            $this->baskets->save($validated);
            if ($basket->kind === 'weekly' && $basket->referenceCycleId !== null) {
                $this->weeklySource->closeIfFinished($basket->referenceCycleId);
            }
        });
    }
}
