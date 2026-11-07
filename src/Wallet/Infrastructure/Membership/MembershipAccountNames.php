<?php

namespace Guixa\Wallet\Infrastructure\Membership;

use Guixa\Membership\Application\GetAccountNames;
use Guixa\Wallet\Domain\AccountNames;

/** Answers Wallet's question about movement authors through Membership's use case. */
final class MembershipAccountNames implements AccountNames
{
    public function __construct(private readonly GetAccountNames $getAccountNames) {}

    public function forAccounts(array $accountIds): array
    {
        return ($this->getAccountNames)($accountIds);
    }
}
