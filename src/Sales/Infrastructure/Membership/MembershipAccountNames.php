<?php

namespace Guixa\Sales\Infrastructure\Membership;

use Guixa\Membership\Application\GetAccountNames;
use Guixa\Sales\Domain\AccountNames;

/** Answers Sales' question about basket authors through Membership's use case. */
final class MembershipAccountNames implements AccountNames
{
    public function __construct(private readonly GetAccountNames $getAccountNames) {}

    public function forAccounts(array $accountIds): array
    {
        return ($this->getAccountNames)($accountIds);
    }
}
