<?php

namespace Guixa\Sales\Application;

use Guixa\Shared\Application\Actor;

interface WalletLedger
{
    public function charge(Actor $actor, string $householdId, string $basketId, int $totalCents): void;

    public function balance(Actor $actor, string $householdId): int;

    public function correct(Actor $actor, string $householdId, string $basketId, int $amountCents, string $reason, string $correctionId): void;
}
