<?php

namespace Guixa\Sales\Infrastructure\Wallet;

use Guixa\Sales\Application\WalletLedger;
use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Application\ChargeForBasket;
use Guixa\Wallet\Application\GetBalance;
use Guixa\Wallet\Application\RecordCorrection;

final class WalletBasketLedger implements WalletLedger
{
    public function __construct(private readonly ChargeForBasket $charge, private readonly GetBalance $balance,
        private readonly RecordCorrection $correction) {}

    public function charge(Actor $actor, string $householdId, string $basketId, int $totalCents): void
    {
        ($this->charge)($actor, $householdId, $basketId, $totalCents);
    }

    public function balance(Actor $actor, string $householdId): int
    {
        return ($this->balance)($actor, $householdId);
    }

    public function correct(Actor $actor, string $householdId, string $basketId, int $amountCents, string $reason, string $correctionId): void
    {
        ($this->correction)($actor, $householdId, $amountCents, $reason, basketId: $basketId, correctionId: $correctionId);
    }
}
