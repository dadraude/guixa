<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;

/** Internal query for Stock: who recorded a movement, kept after the account is deactivated (R28a); it takes no actor. */
final class GetAccountNames
{
    public function __construct(private readonly AccountRepository $accounts) {}

    /**
     * @param  list<string>  $accountIds
     * @return array<string, string> name by account id; unknown accounts are left out
     */
    public function __invoke(array $accountIds): array
    {
        return $this->accounts->namesByIds(array_values(array_unique($accountIds)));
    }
}
