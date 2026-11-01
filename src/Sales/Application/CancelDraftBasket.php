<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;

final class CancelDraftBasket
{
    public function __construct(private readonly BasketRepository $baskets, private readonly TransactionRunner $transactions, private readonly Clock $clock) {}

    public function __invoke(Actor $actor, string $basketId): void
    {
        $actor->assertCanManageSales();
        $this->transactions->run(function () use ($basketId, $actor): void {
            $basket = $this->baskets->lock($basketId) ?? throw BasketNotFound::withId($basketId);
            if ($basket->state !== 'draft') {
                throw BasketNotEditable::create();
            }
            $this->baskets->save($basket->cancelled($actor->accountId, $this->clock->now()));
        });
    }
}
