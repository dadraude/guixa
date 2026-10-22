<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;

/** Asked by the login adapter after the password check. */
final class CanLogIn
{
    public function __construct(private readonly AccountRepository $accounts) {}

    public function __invoke(string $accountId): bool
    {
        return $this->accounts->get($accountId)?->canLogIn() ?? false;
    }
}
