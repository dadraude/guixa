<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Shared\Application\Actor;

final class ListBaskets
{
    public function __construct(private readonly BasketRepository $baskets) {}

    /** @return list<BasketData> */
    public function __invoke(Actor $actor): array
    {
        $actor->assertManager();

        return array_map(BasketData::from(...), $this->baskets->list());
    }
}
