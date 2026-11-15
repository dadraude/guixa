<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketCollectionEvent;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketCollectionCorrectionIdentityConflict;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Sales\Domain\Error\InvalidBasketCollectionCorrection;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class CorrectBasketCollection
{
    public function __construct(private readonly BasketRepository $baskets,
        private readonly TransactionRunner $transactions, private readonly Clock $clock) {}

    public function __invoke(Actor $actor, string $basketId, string $reason, string $correctionId): void
    {
        Gate::forUser($actor)->authorize('manage-sales');
        $reason = trim($reason);
        if ($reason === '' || trim($correctionId) === '') {
            throw InvalidBasketCollectionCorrection::create();
        }

        $this->transactions->run(function () use ($actor, $basketId, $reason, $correctionId): void {
            $basket = $this->baskets->lock($basketId) ?? throw BasketNotFound::withId($basketId);
            $correction = new BasketCollectionEvent($correctionId, $basketId, 'corrected',
                $actor->accountId, $this->clock->now(), $reason);
            foreach ($basket->collectionEvents as $existing) {
                if ($existing->id !== $correctionId) {
                    continue;
                }
                if (! $existing->matches($correction)) {
                    throw BasketCollectionCorrectionIdentityConflict::create();
                }

                return;
            }
            $this->baskets->save($basket->collectionCorrected($correction));
        });
    }
}
