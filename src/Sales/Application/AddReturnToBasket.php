<?php

namespace Guixa\Sales\Application;

use DomainException;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketLineKind;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Sales\Domain\Error\InvalidReturnQuantity;
use Guixa\Sales\Domain\Error\ReturnItemUnavailable;
use Guixa\Sales\Domain\ReturnItems;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Support\Facades\Gate;

final class AddReturnToBasket
{
    public function __construct(private readonly BasketRepository $baskets, private readonly ReturnItems $items,
        private readonly TransactionRunner $transactions, private readonly Clock $clock) {}

    public function __invoke(Actor $actor, string $basketId, string $returnItemId, string $quantity): void
    {
        Gate::forUser($actor)->authorize('manage-sales');
        try {
            $addition = Quantity::fromString($quantity);
        } catch (DomainException) {
            throw InvalidReturnQuantity::create();
        }
        if ($addition->isZero() || ! $addition->isWhole()) {
            throw InvalidReturnQuantity::create();
        }

        $this->transactions->run(function () use ($basketId, $returnItemId, $addition): void {
            $basket = $this->baskets->lock($basketId) ?? throw BasketNotFound::withId($basketId);
            if (! $basket->state->isDraft()) {
                throw BasketNotEditable::create();
            }
            $item = $this->items->get($returnItemId);
            if ($item === null) {
                throw ReturnItemUnavailable::create();
            }

            $lines = $basket->lines;
            foreach ($lines as $index => $line) {
                if ($line->kind->isReturn() && $line->returnItemId === $returnItemId && $line->removedAt === null) {
                    try {
                        $combined = Quantity::fromString(
                            Quantity::ofThousandths($line->quantity->thousandths + $addition->thousandths)->toString()
                        );
                    } catch (DomainException) {
                        throw InvalidReturnQuantity::create();
                    }
                    $lines[$index] = $line->withQuantity($combined);
                    $this->baskets->save($basket->withLines($lines));

                    return;
                }
            }
            $lines[] = new BasketLine($this->baskets->nextId(), $basketId, null, $item->name, 'item',
                $item->amountCents, false, null, $addition, $this->clock->now(),
                kind: BasketLineKind::Return, returnItemId: $returnItemId);
            $this->baskets->save($basket->withLines($lines));
        });
    }
}
