<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Quantity;

/**
 * Internal call from Ordering when a product received as 0 arrives after a reopen. An unvalidated base takes the whole source,
 * reviving a nothing-to-collect one. When the base is validated, the late lines join the household's open late draft or start
 * a new one from the same source order; a later allocation of a line already on that draft reallocates it, to zero too, which leaves the draft with nothing to collect; validated baskets never change (R18, R19, R24a, S30, S91). Call it inside the caller's transaction.
 */
final class AddLateWeeklyLines
{
    public function __construct(private readonly BasketRepository $baskets, private readonly SyncWeeklyBasket $sync, private readonly BuildWeeklyBasketLines $lines, private readonly Clock $clock) {}

    /** @param list<string> $productIds the products that arrived late */
    public function __invoke(WeeklyBaseData $source, array $productIds): void
    {
        $base = $this->baskets->getBySourceOrder($source->sourceOrderId);
        if ($base === null || in_array($base->state, ['draft', 'nothing_to_collect'], true)) {
            ($this->sync)($source);

            return;
        }

        $listed = new WeeklyBaseData($source->sourceOrderId, $source->cycleId, $source->householdId, $source->householdName,
            array_values(array_filter($source->lines, fn (WeeklyBaseLineData $line) => in_array($line->productId, $productIds, true))));
        $open = $this->baskets->getOpenLateBySourceOrder($source->sourceOrderId);
        if ($open !== null) {
            // A correction of a line already on the late draft follows the allocation, down to zero.
            $locked = $this->baskets->lock($open->id) ?? throw BasketNotFound::withId($open->id);
            $this->baskets->save($locked->withLateLines(($this->lines)($listed, $locked->id)));

            return;
        }

        $arrived = new WeeklyBaseData($listed->sourceOrderId, $listed->cycleId, $listed->householdId, $listed->householdName,
            array_values(array_filter($listed->lines, fn (WeeklyBaseLineData $line) => ! Quantity::fromString($line->allocated)->isZero())));
        if ($arrived->lines === []) {
            return;
        }

        $id = $this->baskets->nextId();
        $this->baskets->save(new Basket($id, $source->householdId, $source->householdName, 'draft', null, $this->clock->now(),
            ($this->lines)($arrived, $id), kind: 'weekly', sourceOrderId: $source->sourceOrderId, referenceCycleId: $source->cycleId, late: true));
    }
}
