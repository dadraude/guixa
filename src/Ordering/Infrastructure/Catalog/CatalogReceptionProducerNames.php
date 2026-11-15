<?php

namespace Guixa\Ordering\Infrastructure\Catalog;

use Guixa\Catalog\Application\ReadProducerNames;
use Guixa\Ordering\Domain\ReceptionProducerNames;

/** Answers Ordering's producer names through Catalog's use case (A4). */
final class CatalogReceptionProducerNames implements ReceptionProducerNames
{
    public function __construct(private readonly ReadProducerNames $readNames) {}

    public function of(array $producerIds): array
    {
        return ($this->readNames)($producerIds);
    }
}
