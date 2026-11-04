<?php

namespace Guixa\Membership\Application;

final readonly class OpenHouseholdBasketData
{
    public function __construct(public string $basketId, public string $status, public string $date) {}
}
