<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Guixa\Stock\Domain\ReturnItem;
use Guixa\Stock\Domain\ReturnItemRepository;

final class CreateReturnItem
{
    public function __construct(private readonly ReturnItemRepository $items) {}

    /** @return string the new return item's id */
    public function __invoke(Actor $actor, string $name, int $amountCents): string
    {
        $actor->assertManager();

        $item = ReturnItem::create($this->items->nextId(), $name, Money::ofCents($amountCents));
        $this->items->add($item);

        return $item->id;
    }
}
