<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Shared\Application\Actor;

/** The account comes back without a household; a manager links it again (R35, R36b). */
final class ReactivateAccount
{
    public function __construct(private readonly AccountRepository $accounts) {}

    public function __invoke(Actor $actor, string $accountId): void
    {
        $actor->assertManager();

        $account = $this->accounts->get($accountId) ?? throw AccountNotFound::withId($accountId);

        $account->reactivate();
        $this->accounts->save($account);
    }
}
