<?php

namespace Guixa\Ordering\Infrastructure\Stock;

use DomainException;
use Guixa\Ordering\Application\StockSeller;
use Guixa\Ordering\Domain\Error\NotEnoughStock;
use Guixa\Ordering\Domain\StockLine;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Application\GetStockOffer;
use Guixa\Stock\Application\SellStock;

/** Sells stock lines through Stock's use cases (A4). */
final class StockModuleStockSeller implements StockSeller
{
    public function __construct(
        private readonly SellStock $sellStock,
        private readonly GetStockOffer $getStockOffer,
    ) {}

    public function sell(Actor $actor, StockLine $line, string $orderId): void
    {
        try {
            ($this->sellStock)($actor, $line->stockProductId, $line->quantity->toString(), $orderId);
        } catch (DomainException $failure) {
            // Stock's own errors stay out of Ordering (A4): re-read the stock to tell a shortage from any other refusal.
            $available = ($this->getStockOffer)($line->stockProductId)->available;

            if (Quantity::fromString($available)->thousandths >= $line->quantity->thousandths) {
                throw $failure;
            }

            throw NotEnoughStock::forProduct($line->name, $available, $line->quantity->toString());
        }
    }
}
