<?php

namespace Guixa\Sales\Application;

use Guixa\Shared\Application\Actor;

final class GetBasketBalance
{
    public function __construct(private readonly WalletLedger $wallet) {}

    public function __invoke(Actor $actor, string $householdId): int
    {
        $actor->assertManager();

        return $this->wallet->balance($actor, $householdId);
    }
}
