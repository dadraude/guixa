<?php

namespace Guixa\Sales\Infrastructure\Stock;

use DomainException;
use Guixa\Sales\Domain\ReturnItems;
use Guixa\Sales\Domain\ValueObject\ReturnItemSnapshot;
use Guixa\Stock\Application\GetReturnItem;

final class StockModuleReturnItems implements ReturnItems
{
    public function __construct(private readonly GetReturnItem $get) {}

    public function get(string $id): ?ReturnItemSnapshot
    {
        try {
            $item = ($this->get)($id);
        } catch (DomainException) {
            return null;
        }

        return new ReturnItemSnapshot($item->id, $item->name, $item->amountCents, $item->active);
    }
}
