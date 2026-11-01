<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class ListBaskets
{
    public function __construct(private readonly BasketRepository $baskets) {}

    /** @return list<BasketData> */
    public function __invoke(Actor $actor): array
    {
        Gate::forUser($actor)->authorize('manage-sales');

        return array_map(BasketData::from(...), $this->baskets->list());
    }
}
