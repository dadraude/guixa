<?php

namespace Guixa\Membership\Infrastructure\Catalog;

use Guixa\Catalog\Application\GetProducer;
use Guixa\Membership\Domain\ProducerDirectory;

/** Answers Membership's questions about producers through Catalog's use cases (A4). */
final class CatalogProducerDirectory implements ProducerDirectory
{
    public function __construct(private readonly GetProducer $getProducer) {}

    public function exists(string $producerId): bool
    {
        return ($this->getProducer)($producerId) !== null;
    }
}
