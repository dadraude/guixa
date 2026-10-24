<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;

/** Internal command for Catalog, which takes no actor. Gives the producer role back when its producer is reactivated (R3a). */
final class RestoreProducerRole
{
    public function __construct(private readonly AccountRepository $accounts) {}

    /** A producer with no account yet is left alone. */
    public function __invoke(string $producerId): void
    {
        $account = $this->accounts->ofProducer($producerId);

        if ($account === null) {
            return;
        }

        $account->restoreProducerRole();
        $this->accounts->save($account);
    }
}
