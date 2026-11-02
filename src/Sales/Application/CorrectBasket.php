<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketAdjustment;
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
        private readonly TransactionRunner $transactions, private readonly Clock $clock) {}

    public function __invoke(Actor $actor, string $basketId, int $amountCents, string $reason, string $correctionId): void
    {
        Gate::forUser($actor)->authorize('manage-sales');
        if ($amountCents === 0 || trim($reason) === '' || trim($correctionId) === '') {
            throw InvalidBasketCorrection::create();
        }

        $this->transactions->run(function () use ($actor, $basketId, $amountCents, $reason, $correctionId): void {
            $basket = $this->baskets->lock($basketId) ?? throw BasketNotFound::withId($basketId);
            if (! in_array($basket->state, ['validated', 'collected'], true)) {
                throw InvalidBasketCorrection::create();
            }
            $adjustment = new BasketAdjustment($correctionId, $basketId, $amountCents, trim($reason),
                $actor->accountId, $this->clock->now());
            foreach ($basket->adjustments as $existing) {
                if ($existing->id !== $correctionId) {
                    continue;
                }
                if (! $existing->matches($adjustment)) {
                    throw BasketCorrectionIdentityConflict::create();
                }

                return;
            }

            $corrected = $basket->withAdjustment($adjustment);
            $this->baskets->save($corrected);
            $this->wallet->correct($actor, $basket->householdId, $basketId, $amountCents, $adjustment->reason, $correctionId);
        });
    }
}
