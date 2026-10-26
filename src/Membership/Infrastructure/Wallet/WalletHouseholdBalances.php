<?php

namespace Guixa\Membership\Infrastructure\Wallet;

use Guixa\Membership\Domain\HouseholdBalances;
use Guixa\Wallet\Application\ReadHouseholdBalance;

final class WalletHouseholdBalances implements HouseholdBalances
{
    public function __construct(private readonly ReadHouseholdBalance $readHouseholdBalance) {}

    public function balanceCents(string $householdId): int
    {
        return ($this->readHouseholdBalance)($householdId);
    }
}
