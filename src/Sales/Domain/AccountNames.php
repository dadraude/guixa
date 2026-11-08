<?php

namespace Guixa\Sales\Domain;

/** Sales' read port for the names of the accounts that recorded basket adjustments and collection events. */
interface AccountNames
{
    /**
     * @param  list<string>  $accountIds
     * @return array<string, string> name by account id, deactivated accounts included; unknown accounts are left out
     */
    public function forAccounts(array $accountIds): array;
}
