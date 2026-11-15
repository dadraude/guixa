<?php

namespace Guixa\Sales\Infrastructure\Ordering;

use Guixa\Ordering\Application\CloseCycleAfterBasketValidation;
use Guixa\Ordering\Application\LockWeeklyBasketSource;
use Guixa\Sales\Application\WeeklyBasketSource;
use Guixa\Sales\Application\WeeklySourceOrders;

final class OrderingWeeklyBasketSource implements WeeklyBasketSource
{
    public function __construct(
        private readonly LockWeeklyBasketSource $lock,
        private readonly CloseCycleAfterBasketValidation $close,
        private readonly WeeklySourceOrders $sourceOrders,
    ) {}

    public function lockSettled(string $cycleId): void
    {
        ($this->lock)($cycleId);
    }

    public function closeIfFinished(string $cycleId): void
    {
        ($this->close)($cycleId);
    }

    public function cyclesOf(array $sourceOrderIds): array
    {
        return $this->sourceOrders->cyclesOf($sourceOrderIds);
    }

    public function ofCycle(string $cycleId): array
    {
        return $this->sourceOrders->ofCycle($cycleId);
    }
}
