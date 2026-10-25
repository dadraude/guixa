<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;

/** Charges an absent household's order lines and freezes its pickup, without selling stock (R24a). */
final class MarkPickupNotCollected
{
    public function __construct(
        private readonly PickupLocker $pickups,
        private readonly OrderRepository $orders,
        private readonly WalletLedger $wallet,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    public function __invoke(Actor $actor, string $cycleId, string $householdId): void
    {
        $actor->assertManager();

        $this->transactions->run(function () use ($actor, $cycleId, $householdId) {
            $order = $this->pickups->lock($cycleId, $householdId, completing: true);
            $total = $order->markNotCollected($this->clock->now());
            $this->wallet->charge($actor, $householdId, $cycleId, $total->cents);
            $this->orders->add($order);
            $this->pickups->closeCycleIfDone($cycleId, $this->clock->now());
        });
    }
}
