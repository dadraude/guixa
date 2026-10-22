<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Shared\Application\Actor;

final class GrantManagerRole
{
    public function __construct(private readonly AccountRepository $accounts) {}

    public function __invoke(Actor $actor, string $accountId): void
    {
        $actor->assertManager();

        $account = $this->accounts->get($accountId) ?? throw AccountNotFound::withId($accountId);

        $account->grantManager();
        $this->accounts->save($account);
    }
}
