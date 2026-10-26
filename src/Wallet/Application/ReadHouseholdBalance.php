<?php

namespace Guixa\Wallet\Application;

use Guixa\Wallet\Domain\MovementRepository;

/** Internal query for Membership, which settles households its own manager-only use cases already authorised (R35a); it takes no actor. */
final class ReadHouseholdBalance
{
    public function __construct(private readonly MovementRepository $movements) {}

    /** @return int the household's balance in cents; it may be negative (R30) */
    public function __invoke(string $householdId): int
    {
        return $this->movements->balanceOf($householdId)->cents;
    }
}
