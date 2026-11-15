<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;

final class ListActiveStock
{
    public function __construct(private readonly ListStock $listStock) {}

    /** @return list<StockLevelData> */
    public function __invoke(Actor $actor): array
    {
        return array_values(array_filter(
            ($this->listStock)($actor),
            fn (StockLevelData $level) => $level->active,
        ));
    }
}
