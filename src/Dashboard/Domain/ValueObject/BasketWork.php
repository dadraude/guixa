<?php

namespace Guixa\Dashboard\Domain\ValueObject;

use Guixa\Dashboard\Domain\ActionKind;

/** One basket that needs an action; no cycle means an independent stock basket. */
final readonly class BasketWork
{
    public function __construct(
        public string $basketId,
        public ?string $cycleId,
        public ?string $scheduledDate,
        public ActionKind $action = ActionKind::CollectBasket,
    ) {}
}
