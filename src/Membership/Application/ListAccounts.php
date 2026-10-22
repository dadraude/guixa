<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;

final class ListAccounts
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly HouseholdRepository $households,
    ) {}

    /** @return list<AccountSummary> */
    public function __invoke(Actor $actor): array
    {
        $actor->assertManager();

        $householdNames = [];
        foreach ($this->households->list() as $household) {
            $householdNames[$household->id] = $household->name;
        }

        return array_map(
            fn (Account $account) => new AccountSummary(
                $account->id,
                $account->name,
                $account->email->value,
                array_map(fn (Role $role) => $role->value, $account->roles),
                $account->householdId,
                $account->householdId === null ? null : ($householdNames[$account->householdId] ?? null),
                $account->isActive(),
                ! $account->hasAcceptedInvitation(),
            ),
            $this->accounts->list(),
        );
    }
}
