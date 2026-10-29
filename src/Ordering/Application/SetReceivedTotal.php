<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\ManualAllocationsWouldBeLost;
use Guixa\Ordering\Domain\Error\ProductNotInReception;
use Guixa\Ordering\Domain\Error\RedistributionCannotExcludeCompleted;
use Guixa\Ordering\Domain\Error\RedistributionChoiceRequired;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProportionalAllocator;
use Guixa\Ordering\Domain\ReceivedTotal;
use Guixa\Ordering\Domain\ReceivedTotalRepository;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Ordering\Domain\ValueObject\ProportionalClaim;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Quantity;

/** Way A: sets what was received of a product and shares it among the households that ordered it (R15, S10-S12, S31). */
final class SetReceivedTotal
{
    private readonly ProportionalAllocator $allocator;

    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly ReceptionLoader $loader,
        private readonly ReceivedTotalRepository $receivedTotals,
        private readonly TransactionRunner $transactions,
        private readonly WalletLedger $wallet,
        private readonly Clock $clock,
    ) {
        $this->allocator = new ProportionalAllocator;
    }

    /**
     * @param  string  $received  at most 3 decimals; whole units for a non-variable product
     * @param  bool  $overwriteManual  the manager confirmed losing the allocations set by hand; without it nothing is stored (R15)
     */
    public function __invoke(Actor $actor, string $cycleId, string $producerId, string $productId, string $received, bool $overwriteManual = false, ?bool $includeCompleted = null): void
    {
        $actor->assertManager();

        $total = Quantity::fromString($received);

        // The cycle lock serialises every reception change, so the orders read here cannot change before they are saved.
        $this->transactions->run(function () use ($actor, $cycleId, $producerId, $productId, $total, $overwriteManual, $includeCompleted) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->assertReceptionEditable($producerId);
            $loaded = $this->loader->load($cycle, $producerId);

            $product = $loaded->reception->product($productId) ?? throw ProductNotInReception::withId($productId);
            $this->loader->lockWeekly($productId) ?? throw ProductNotInReception::withId($productId);
            $product->assertInStep($total);

            $frozenHouseholds = [];
            $frozenAllocated = 0;

            foreach ($loaded->orders as $order) {
                if (! $order->isFrozen() || ! $order->hasLine($productId)) {
                    continue;
                }

                $frozenHouseholds[$order->householdId] = true;
            }

            if ($frozenHouseholds !== [] && $includeCompleted === null) {
                throw RedistributionChoiceRequired::create();
            }

            if ($includeCompleted === true) {
                $frozenHouseholds = [];
            }

            foreach ($product->lines as $line) {
                if (isset($frozenHouseholds[$line->householdId])) {
                    $frozenAllocated += $line->allocated->thousandths;
                }
            }

            $claims = array_values(array_filter($product->claims(), fn (ProportionalClaim $claim) => ! isset($frozenHouseholds[$claim->householdId])));
            if ($frozenHouseholds !== [] && ($total->thousandths < $frozenAllocated || $claims === [])) {
                throw RedistributionCannotExcludeCompleted::create();
            }

            $target = Quantity::ofThousandths($total->thousandths - $frozenAllocated);
            $shares = $this->allocator->allocate($target, $product->step(), $claims);

            $manual = array_values(array_filter($product->manuallyAllocatedHouseholds(), fn (string $householdId) => ! isset($frozenHouseholds[$householdId])));

            if ($manual !== [] && ! $overwriteManual) {
                throw ManualAllocationsWouldBeLost::of($productId, $manual);
            }

            foreach ($loaded->orders as $order) {
                if (! $order->hasLine($productId) || isset($loaded->marksByOrder[$order->id][$productId])) {
                    continue;
                }

                $share = $shares[$order->householdId] ?? Quantity::ofThousandths(0);

                if ($order->isFrozen()) {
                    if ($includeCompleted !== true) {
                        continue;
                    }

                    $adjustment = $order->appendAdjustment($productId, $share, $this->orders->nextId(), $this->clock->now(), $actor->accountId);

                    if ($adjustment !== null) {
                        $this->wallet->correct($actor, $order->householdId, $cycleId, -$adjustment->amount->cents, $adjustment->name);
                    }
                } else {
                    $order->reallocate($productId, $share);
                }
                $this->orders->add($order);
            }

            $this->receivedTotals->set(new ReceivedTotal($cycleId, $producerId, $productId, $total));
            $cycle->clearValidation($producerId);
            $this->cycles->add($cycle);
        });
    }
}
