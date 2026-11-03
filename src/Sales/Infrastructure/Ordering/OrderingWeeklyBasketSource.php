<?php

namespace Guixa\Sales\Infrastructure\Ordering;

use Guixa\Ordering\Application\CloseCycleAfterBasketValidation;
use Guixa\Ordering\Application\LockWeeklyBasketSource;
use Guixa\Sales\Application\WeeklyBasketSource;

final class OrderingWeeklyBasketSource implements WeeklyBasketSource
{
    public function __construct(private readonly LockWeeklyBasketSource $lock, private readonly CloseCycleAfterBasketValidation $close) {}

    public function lockSettled(string $cycleId): void
    {
        ($this->lock)($cycleId);
    }

    public function closeIfFinished(string $cycleId): void
    {
        ($this->close)($cycleId);
    }
}
