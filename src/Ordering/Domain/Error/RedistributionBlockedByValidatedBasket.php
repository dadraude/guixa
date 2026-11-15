<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

/** A reopened reception cannot redistribute a product a validated basket contains; staff correct that basket instead (R18, R23, S63). */
final class RedistributionBlockedByValidatedBasket extends DomainException
{
    private function __construct(string $message, public readonly string $basketId)
    {
        parent::__construct($message);
    }

    public static function forBasket(string $basketId, string $productId): self
    {
        return new self("The validated basket {$basketId} contains product {$productId}: correct that basket instead of redistributing.", $basketId);
    }
}
