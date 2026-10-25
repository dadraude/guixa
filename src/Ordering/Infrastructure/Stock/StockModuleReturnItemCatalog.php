<?php

namespace Guixa\Ordering\Infrastructure\Stock;

use DomainException;
use Guixa\Ordering\Domain\ReturnItemCatalog;
use Guixa\Ordering\Domain\ValueObject\ReturnItemOffer;
use Guixa\Shared\Domain\Money;
use Guixa\Stock\Application\GetReturnItem;

/** Answers Ordering's questions about return items through Stock's use cases (A4). */
final class StockModuleReturnItemCatalog implements ReturnItemCatalog
{
    public function __construct(private readonly GetReturnItem $getReturnItem) {}

    public function find(string $returnItemId): ?ReturnItemOffer
    {
        // Stock's query reports an unknown id with its not-found domain error, which A4 keeps out of Ordering; it raises no other.
        try {
            $item = ($this->getReturnItem)($returnItemId);
        } catch (DomainException) {
            return null;
        }

        return new ReturnItemOffer($item->id, $item->name, Money::ofCents($item->amountCents));
    }
}
