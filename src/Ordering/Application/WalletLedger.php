<?php

namespace Guixa\Ordering\Application;

use Guixa\Shared\Application\Actor;

/** What Ordering needs from Wallet when it completes a pickup (R21, R22). */
interface WalletLedger
{
    /** Records a cash top-up of the household that is not tied to any cycle (R29). */
    public function topUp(Actor $actor, string $householdId, int $amountCents): void;

    /** Charges the household for the cycle; a negative total is a credit and zero records nothing (R22, R23). */
    public function charge(Actor $actor, string $householdId, string $cycleId, int $totalCents): void;
}
