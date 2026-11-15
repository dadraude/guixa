<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Stock\Domain\ReturnItemRepository;
use Illuminate\Support\Facades\Gate;

final class ListReturnItems
{
    public function __construct(private readonly ReturnItemRepository $items) {}

    /** @return list<ReturnItemData> */
    public function __invoke(Actor $actor): array
    {
        Gate::forUser($actor)->authorize('view-return-items');

        return array_map(
            fn ($item) => new ReturnItemData($item->id, $item->name(), $item->amount()->cents, $item->isActive()),
            $this->items->list(),
        );
    }
}
