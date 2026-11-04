<?php

namespace Guixa\Catalog\Application;

use Guixa\Shared\Application\Actor;

final class ListActiveProducers
{
    public function __construct(private readonly ListProducers $listProducers) {}

    /** @return list<ProducerData> */
    public function __invoke(Actor $actor): array
    {
        return array_values(array_filter(
            ($this->listProducers)($actor),
            fn (ProducerData $producer) => $producer->active,
        ));
    }
}
