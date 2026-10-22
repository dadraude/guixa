<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Membership\Domain\Error\LastManager;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

final class RemoveManagerRole
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $accountId): void
    {
        $actor->assertManager();

        $this->transactions->run(function () use ($accountId): void {
            // Locked first, so two removals cannot each see the other manager still there (R36a).
            $this->accounts->lockManagers();

            $account = $this->accounts->get($accountId) ?? throw AccountNotFound::withId($accountId);

            if ($account->isActiveManager() && ! $this->accounts->hasOtherActiveManager($account->id)) {
                throw LastManager::forAccount($account->id);
            }

            $account->removeManager();
            $this->accounts->save($account);
        });
    }
}
