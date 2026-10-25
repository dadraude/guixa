<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\StockLine;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;

/** Freezes a pending pickup, sells its stock and charges or credits the household, all or nothing (R21, R22, R23). */
final class CompletePickup
{
    public function __construct(
        private readonly PickupLocker $pickups,
        private readonly OrderRepository $orders,
        private readonly StockSeller $stock,
        private readonly WalletLedger $wallet,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    /** @param  ?int  $topUpCents  a cash top-up recorded at the same time, above zero (R21, R29) */
    public function __invoke(Actor $actor, string $cycleId, string $householdId, ?int $topUpCents = null): void
    {
        $actor->assertManager();

        $this->transactions->run(function () use ($actor, $cycleId, $householdId, $topUpCents) {
            $order = $this->pickups->lock($cycleId, $householdId);
            $total = $order->complete($this->clock->now());

            foreach ($this->inStockProductOrder($order->stockLines()) as $line) {
                $this->stock->sell($actor, $line, $order->id);
            }

            if ($topUpCents !== null) {
                $this->wallet->topUp($actor, $householdId, $topUpCents);
            }

            $this->wallet->charge($actor, $householdId, $cycleId, $total->cents);
            $this->orders->add($order);
        });
    }

    /**
     * Two pickups selling the same products lock them in the same order, so they cannot deadlock.
     *
     * @param  list<StockLine>  $lines
     * @return list<StockLine>
     */
    private function inStockProductOrder(array $lines): array
    {
        usort($lines, fn (StockLine $a, StockLine $b) => $a->stockProductId <=> $b->stockProductId);

        return $lines;
    }
}
