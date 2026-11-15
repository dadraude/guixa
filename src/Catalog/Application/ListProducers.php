<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class ListProducers
{
    public function __construct(private readonly ProducerRepository $producers) {}

    /** @return list<ProducerData> */
    public function __invoke(Actor $actor): array
    {
        Gate::forUser($actor)->authorize('view-producers');

        return array_map(ProducerData::of(...), $this->producers->list());
    }
}
