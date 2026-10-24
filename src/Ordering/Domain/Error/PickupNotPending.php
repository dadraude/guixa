<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class PickupNotPending extends DomainException
{
    public static function forOrder(string $orderId): self
    {
        return new self("The pickup of order {$orderId} is not pending, so it cannot be changed.");
    }
}
