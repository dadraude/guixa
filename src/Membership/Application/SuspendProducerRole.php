<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;

/** Internal command for Catalog, which takes no actor. Takes the producer role away while its producer is deactivated; the account keeps its other roles (R3a). */
final class SuspendProducerRole
{
    public function __construct(private readonly AccountRepository $accounts) {}

    /** A producer with no account yet is left alone. */
    public function __invoke(string $producerId): void
    {
        $account = $this->accounts->ofProducer($producerId);

        if ($account === null) {
            return;
        }

        $account->suspendProducerRole();
        $this->accounts->save($account);
    }
}
