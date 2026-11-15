<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class MarkBasketCollected
{
    public function __construct(private readonly BasketRepository $baskets, private readonly TransactionRunner $transactions, private readonly Clock $clock) {}

    public function __invoke(Actor $actor, string $basketId): void
    {
        $this->transactions->run(function () use ($actor, $basketId): void {
            $basket = $this->baskets->lock($basketId) ?? throw BasketNotFound::withId($basketId);
            Gate::forUser($actor)->authorize('collectFor', ['household', $basket->householdId]);
            if ($basket->state->isCollected()) {
                return;
            }
            if (! $basket->state->isValidated()) {
                throw BasketNotEditable::create();
            }
            $this->baskets->save($basket->collected($actor->accountId, $this->clock->now(), $this->baskets->nextId()));
        });
    }
}
