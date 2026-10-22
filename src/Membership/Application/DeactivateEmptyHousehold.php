<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Domain\Clock;

/** A household left without members is deactivated (R35a). Used by every use case that takes a member out. */
final class DeactivateEmptyHousehold
{
    public function __construct(
        private readonly HouseholdRepository $households,
        private readonly AccountRepository $accounts,
        private readonly Clock $clock,
    ) {}

    public function __invoke(?string $householdId): void
    {
        if ($householdId === null || $this->accounts->membersOf($householdId) !== []) {
            return;
        }

        $household = $this->households->get($householdId);

        if ($household === null || ! $household->isActive()) {
            return;
        }

        $household->deactivate($this->clock->now());
        $this->households->save($household);
    }
}
