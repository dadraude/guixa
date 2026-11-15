<?php

namespace Guixa\Stock\Infrastructure\Membership;

use Guixa\Membership\Application\GetAccountNames;
use Guixa\Stock\Domain\AccountNames;

/** Answers Stock's question about movement authors through Membership's use case. */
final class MembershipAccountNames implements AccountNames
{
    public function __construct(private readonly GetAccountNames $getAccountNames) {}

    public function forAccounts(array $accountIds): array
    {
        return ($this->getAccountNames)($accountIds);
    }
}
