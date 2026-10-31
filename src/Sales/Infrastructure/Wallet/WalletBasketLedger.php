<?php

namespace Guixa\Sales\Infrastructure\Wallet;

use Guixa\Sales\Application\WalletLedger;
use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Application\ChargeForBasket;
use Guixa\Wallet\Application\GetBalance;

final class WalletBasketLedger implements WalletLedger
{
    public function __construct(private readonly ChargeForBasket $charge, private readonly GetBalance $balance) {}

    public function charge(Actor $actor, string $householdId, string $basketId, int $totalCents): void
    {
        ($this->charge)($actor, $householdId, $basketId, $totalCents);
    }

    public function balance(Actor $actor, string $householdId): int
    {
        return ($this->balance)($actor, $householdId);
    }
}
