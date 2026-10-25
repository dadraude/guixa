<?php

namespace Guixa\Ordering\Infrastructure\Wallet;

use Guixa\Ordering\Application\WalletLedger;
use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Application\ChargeForPickup;
use Guixa\Wallet\Application\RecordTopUp;

/** Records a pickup's top-up and charge through Wallet's use cases (A4). */
final class WalletModuleLedger implements WalletLedger
{
    public function __construct(
        private readonly RecordTopUp $recordTopUp,
        private readonly ChargeForPickup $chargeForPickup,
    ) {}

    public function topUp(Actor $actor, string $householdId, int $amountCents): void
    {
        ($this->recordTopUp)($actor, $householdId, $amountCents);
    }

    public function charge(Actor $actor, string $householdId, string $cycleId, int $totalCents): void
    {
        ($this->chargeForPickup)($actor, $householdId, $cycleId, $totalCents);
    }
}
