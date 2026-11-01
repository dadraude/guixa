<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;

final class GetWalletHousehold
{
    public function __construct(private readonly HouseholdRepository $households) {}

    public function __invoke(Actor $actor, string $householdId): WalletHousehold
    {
        $actor->assertCanManageWallet();
        $household = $this->households->get($householdId) ?? throw HouseholdNotFound::withId($householdId);

        return new WalletHousehold($household->id, $household->name);
    }
}
