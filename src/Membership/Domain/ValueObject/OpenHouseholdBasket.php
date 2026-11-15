<?php

namespace Guixa\Membership\Domain\ValueObject;

final readonly class OpenHouseholdBasket
{
    public function __construct(public string $basketId, public string $status, public string $date) {}
}
