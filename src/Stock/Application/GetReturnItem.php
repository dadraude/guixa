<?php

namespace Guixa\Stock\Application;

use Guixa\Stock\Domain\Error\ReturnItemNotFound;
use Guixa\Stock\Domain\ReturnItemRepository;

/** Internal query for Ordering (R20a): the return item as it is now. */
final class GetReturnItem
{
    public function __construct(private readonly ReturnItemRepository $items) {}

    public function __invoke(string $returnItemId): ReturnItemData
    {
        $item = $this->items->get($returnItemId) ?? throw ReturnItemNotFound::withId($returnItemId);

        return new ReturnItemData($item->id, $item->name(), $item->amount()->cents, $item->isActive());
    }
}
