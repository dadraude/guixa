<?php

namespace Guixa\Catalog\Domain;

/** What Catalog tells the accounts when a producer is deactivated or reactivated (R3a). */
interface ProducerAccess
{
    /** The producer's account loses the producer role and keeps its other roles. */
    public function suspend(string $producerId): void;

    public function restore(string $producerId): void;
}
