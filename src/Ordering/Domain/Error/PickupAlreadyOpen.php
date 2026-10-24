<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class PickupAlreadyOpen extends DomainException
{
    public static function forOrder(string $orderId): self
    {
        return new self("The household already has a pickup in order {$orderId}.");
    }
}
