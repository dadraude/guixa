<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Guixa\Stock\Domain\Error\ReturnItemNotFound;
use Guixa\Stock\Domain\ReturnItemRepository;

final class UpdateReturnItem
{
    public function __construct(private readonly ReturnItemRepository $items) {}

    public function __invoke(Actor $actor, string $id, string $name, int $amountCents): void
    {
        $actor->assertManager();

        $item = $this->items->get($id) ?? throw ReturnItemNotFound::withId($id);
        $item->change($name, Money::ofCents($amountCents));
        $this->items->add($item);
    }
}
