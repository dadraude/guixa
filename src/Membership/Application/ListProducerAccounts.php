<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Shared\Application\Actor;

/** For the producers admin page: which producers already have their account. */
final class ListProducerAccounts
{
    public function __construct(private readonly AccountRepository $accounts) {}

    /** @return array<string, ProducerAccountView> keyed by producer id */
    public function __invoke(Actor $actor): array
    {
        $actor->assertManager();

        $views = [];

        foreach ($this->accounts->producerAccounts() as $account) {
            $views[(string) $account->producerId] = new ProducerAccountView(
                (string) $account->producerId,
                $account->email->value,
                ! $account->hasAcceptedInvitation(),
            );
        }

        return $views;
    }
}
