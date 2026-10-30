<?php

namespace Guixa\Sales\Application;

use Guixa\Shared\Application\Actor;

interface WalletLedger
{
    public function charge(Actor $actor, string $householdId, string $basketId, int $totalCents): void;

    public function balance(Actor $actor, string $householdId): int;
}
