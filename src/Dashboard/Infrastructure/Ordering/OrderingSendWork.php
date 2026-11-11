<?php

namespace Guixa\Dashboard\Infrastructure\Ordering;

use Guixa\Dashboard\Domain\SendWorkRepository;
use Guixa\Dashboard\Domain\ValueObject\SendWork;
use Guixa\Ordering\Application\DashboardOrderScope;
use Guixa\Ordering\Application\DashboardSendWorkData;
use Guixa\Ordering\Application\ReadDashboardOrderWork;

final class OrderingSendWork implements SendWorkRepository
{
    public function __construct(private readonly ReadDashboardOrderWork $read) {}

    public function pending(): array
    {
        return array_map(
            fn (DashboardSendWorkData $item) => new SendWork($item->cycleId, $item->producerId, $item->producerName, $item->pickupDate),
            ($this->read)(DashboardOrderScope::ManualSend),
        );
    }
}
