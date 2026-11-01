<?php

namespace Guixa\Sales\Application;

use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class GetBasketBalance
{
    public function __construct(private readonly WalletLedger $wallet) {}

    public function __invoke(Actor $actor, string $householdId): int
    {
        Gate::forUser($actor)->authorize('manage-sales');

        return $this->wallet->balance($actor, $householdId);
    }
}
