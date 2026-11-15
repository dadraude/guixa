<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\BasketState;
use Guixa\Shared\Domain\Clock;

/**
 * Internal call from Ordering at settlement: one uncharged base basket per source order, with the ordered and allocated
 * snapshots and the actual quantity starting at the allocation. Replaying it keeps the first basket (R18, R22, S9).
 */
final class CreateWeeklyBasket
{
    public function __construct(private readonly BasketRepository $baskets, private readonly BuildWeeklyBasketLines $lines, private readonly Clock $clock) {}

    public function __invoke(WeeklyBaseData $source): void
    {
        if ($this->baskets->getBySourceOrder($source->sourceOrderId) !== null) {
            return;
        }

        $id = $this->baskets->nextId();
        $basket = new Basket($id, $source->householdId, $source->householdName, BasketState::Draft, null, $this->clock->now(),
            ($this->lines)($source, $id), kind: BasketKind::Weekly, sourceOrderId: $source->sourceOrderId);

        $this->baskets->save($basket->hasContent() ? $basket : $basket->synchronizedWith($basket->lines, $this->clock->now()));
    }
}
