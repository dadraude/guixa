<?php

namespace Guixa\Wallet\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Domain\MovementRepository;

final class GetBalance
{
    public function __construct(private readonly MovementRepository $movements) {}

    /** @return int the household's balance in cents; it may be negative (R30) */
    public function __invoke(Actor $actor, string $householdId): int
    {
        $actor->assertManager();

        return $this->movements->balanceOf($householdId)->cents;
    }
}
