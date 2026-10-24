<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;

/** Internal query for Ordering: where to send a producer its order (R12a). */
final class GetProducerAccountEmail
{
    public function __construct(private readonly AccountRepository $accounts) {}

    /** Null while the producer has no account. */
    public function __invoke(string $producerId): ?string
    {
        return $this->accounts->ofProducer($producerId)?->email->value;
    }
}
