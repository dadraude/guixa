<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketAdjustment;
use Guixa\Sales\Domain\BasketAdjustmentLine;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketCorrectionIdentityConflict;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Sales\Domain\Error\InvalidBasketCorrection;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class CorrectBasket
{
    public function __construct(private readonly BasketRepository $baskets, private readonly WalletLedger $wallet,
        private readonly StockCorrector $stock, private readonly TransactionRunner $transactions, private readonly Clock $clock) {}

    /** @param  array<string, string>  $returnedGoods  Quantity physically returned by product id; empty for an amount-only correction. */
    public function __invoke(Actor $actor, string $basketId, int $amountCents, string $reason, string $correctionId, array $returnedGoods = []): void
    {
        Gate::forUser($actor)->authorize('manage-sales');
        if ($amountCents === 0 || trim($reason) === '' || trim($correctionId) === '') {
            throw InvalidBasketCorrection::create();
        }

        $this->transactions->run(function () use ($actor, $basketId, $amountCents, $reason, $correctionId, $returnedGoods): void {
            $basket = $this->baskets->lock($basketId) ?? throw BasketNotFound::withId($basketId);
            if (! $basket->state->isSettled()) {
                throw InvalidBasketCorrection::create();
            }
            $adjustment = new BasketAdjustment($correctionId, $basketId, $amountCents, trim($reason),
                $actor->accountId, $this->clock->now(), $this->requestedLines($returnedGoods));
            foreach ($basket->adjustments as $existing) {
                if ($existing->id !== $correctionId) {
                    continue;
                }
                if (! $existing->matches($adjustment)) {
                    throw BasketCorrectionIdentityConflict::create();
                }

                return;
            }

            $this->assertReturnable($basket, $adjustment->lines);
            $corrected = $basket->withAdjustment($adjustment);
            $this->baskets->save($corrected);
            foreach ($adjustment->lines as $line) {
                $this->stock->correct($actor, $line->productId, ltrim($line->quantityDelta(), '-'),
                    $adjustment->reason, $basketId, $correctionId);
            }
            $this->wallet->correct($actor, $basket->householdId, $basketId, $amountCents, $adjustment->reason, $correctionId);
        });
    }

    /**
     * Shape only: the effective-quantity bound applies to new corrections, never to a replay.
     *
     * @param  array<string, string>  $returnedGoods
     * @return list<BasketAdjustmentLine>
     */
    private function requestedLines(array $returnedGoods): array
    {
        ksort($returnedGoods);
        $lines = [];
        foreach ($returnedGoods as $productId => $quantity) {
            if (! preg_match('/^\d{1,7}(\.\d{1,3})?\z/', $quantity)) {
                throw InvalidBasketCorrection::create();
            }
            $returned = BasketAdjustmentLine::fromDecimal((string) $productId, $quantity)->quantityDeltaThousandths;
            if ($returned <= 0) {
                throw InvalidBasketCorrection::create();
            }
            $lines[] = new BasketAdjustmentLine((string) $productId, -$returned);
        }

        return $lines;
    }

    /** @param  list<BasketAdjustmentLine>  $lines */
    private function assertReturnable(Basket $basket, array $lines): void
    {
        foreach ($lines as $line) {
            if (-$line->quantityDeltaThousandths > $basket->effectiveQuantity($line->productId)) {
                throw InvalidBasketCorrection::create();
            }
        }
    }
}
