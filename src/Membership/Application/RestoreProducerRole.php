<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\TransactionRunner;

/** Internal Catalog command; producer reactivation must preserve current account roles (R3a). */
final class RestoreProducerRole
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly HouseholdRepository $households,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(string $producerId): void
    {
        $this->transactions->run(function () use ($producerId): void {
            $this->households->lockMembershipChanges();
            $account = $this->accounts->ofProducer($producerId);
            if ($account === null) {
                return;
            }

            $account->restoreProducerRole();
            $this->accounts->save($account);
        });
    }
}
