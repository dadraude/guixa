<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Error\NotEnoughStock;
use Guixa\Ordering\Domain\StockLine;
use Guixa\Shared\Application\Actor;

/** What Ordering needs from Stock when it completes a pickup: sell one stock line (R22). */
interface StockSeller
{
    /**
     * Locks the stock product and takes the line's quantity out of the stock.
     *
     * @throws NotEnoughStock when less than the line's quantity is on hand (R22, S45)
     */
    public function sell(Actor $actor, StockLine $line, string $orderId): void;
}
