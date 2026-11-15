<?php

namespace Guixa\Membership\Application;

use Guixa\Shared\Application\Actor;

final class CountAccounts
{
    public function __construct(private readonly ListAccounts $listAccounts) {}

    public function __invoke(Actor $actor): AccountCountsData
    {
        $accounts = ($this->listAccounts)($actor);

        return new AccountCountsData(
            count($accounts),
            count(array_filter($accounts, fn (AccountSummary $account) => $account->invitationPending)),
        );
    }
}
