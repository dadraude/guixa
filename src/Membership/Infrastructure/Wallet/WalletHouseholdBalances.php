<?php

namespace Guixa\Membership\Infrastructure\Wallet;

use Guixa\Membership\Domain\HouseholdBalances;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Guixa\Wallet\Application\GetBalance;

final class WalletHouseholdBalances implements HouseholdBalances
{
    public function __construct(private readonly GetBalance $getBalance) {}

    public function balanceCents(string $householdId): int
    {
        return ($this->getBalance)(new Actor('system', [Role::Manager]), $householdId);
    }
}
