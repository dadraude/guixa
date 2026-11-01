<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\Gate;

final class ResendInvitation
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly SendInvitation $sendInvitation,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $accountId): void
    {
        Gate::forUser($actor)->authorize('manage');

        $account = $this->accounts->get($accountId) ?? throw AccountNotFound::withId($accountId);
        $account->assertInvitationPending();

        $this->transactions->run(fn () => ($this->sendInvitation)($account));
    }
}
