<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Shared\Domain\Clock;

/**
 * Internal call from Ordering when a settled cycle is distributed again: an unvalidated base follows the current
 * allocations; a validated one is never touched, because Ordering rejects redistributing a product it has a line of.
 * A late weekly basket is not synchronized here: it only grows through AddLateWeeklyLines. A missing base is created (R18, S29, S30).
 */
final class SyncWeeklyBasket
{
    public function __construct(private readonly BasketRepository $baskets, private readonly CreateWeeklyBasket $create, private readonly BuildWeeklyBasketLines $lines, private readonly Clock $clock) {}

    public function __invoke(WeeklyBaseData $source): void
    {
        $stored = $this->baskets->getBySourceOrder($source->sourceOrderId);
        if ($stored === null) {
            ($this->create)($source);

            return;
        }

        $basket = $this->baskets->lock($stored->id);
        if ($basket === null || ! $basket->state->followsSource()) {
            return;
        }

        $this->baskets->save($basket->synchronizedWith(($this->lines)($source, $basket->id), $this->clock->now()));
    }
}
