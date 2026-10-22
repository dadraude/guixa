<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Household;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;

final class ListHouseholds
{
    public function __construct(
        private readonly HouseholdRepository $households,
        private readonly AccountRepository $accounts,
    ) {}

    /** @return list<HouseholdSummary> */
    public function __invoke(Actor $actor): array
    {
        $actor->assertManager();

        return array_map(
            fn (Household $household) => new HouseholdSummary(
                $household->id,
                $household->name,
                $household->isActive(),
                count($this->accounts->membersOf($household->id)),
            ),
            $this->households->list(),
        );
    }
}
