<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Stock\Domain\Error\ReturnItemNotFound;
use Guixa\Stock\Domain\ReturnItemRepository;
use Illuminate\Support\Facades\Gate;

final class ReactivateReturnItem
{
    public function __construct(private readonly ReturnItemRepository $items) {}

    public function __invoke(Actor $actor, string $id): void
    {
        Gate::forUser($actor)->authorize('manage');

        $item = $this->items->get($id) ?? throw ReturnItemNotFound::withId($id);
        $item->reactivate();
        $this->items->add($item);
    }
}
