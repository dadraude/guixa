<?php

namespace Guixa\Sales\Application;

/** A validated basket still to collect, with the cycle and pickup date that give it context; stock baskets have neither. */
final readonly class DashboardBasketWorkData
{
    public function __construct(
        public string $basketId,
        public ?string $cycleId,
        public ?string $pickupDate,
    ) {}
}
