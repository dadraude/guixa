<?php

namespace Guixa\Dashboard\Infrastructure\Ordering;

use Guixa\Dashboard\Domain\ActionKind;
use Guixa\Dashboard\Domain\ReceptionWorkRepository;
use Guixa\Dashboard\Domain\ValueObject\ReceptionWork;
use Guixa\Dashboard\Infrastructure\TranslatesReadFailures;
use Guixa\Ordering\Application\DashboardOrderScope;
use Guixa\Ordering\Application\DashboardReceptionStage;
use Guixa\Ordering\Application\DashboardReceptionWorkData;
use Guixa\Ordering\Application\ReadDashboardOrderWork;

final class OrderingReceptionWork implements ReceptionWorkRepository
{
    use TranslatesReadFailures;

    public function __construct(private readonly ReadDashboardOrderWork $read) {}

    public function pending(): array
    {
        return array_map(fn (DashboardReceptionWorkData $item) => new ReceptionWork(
            $item->cycleId,
            $item->producerId,
            $item->producerName,
            $item->pickupDate,
            $this->action($item->stage),
            $item->allocationDifference,
        ), $this->guarded('reception work', fn () => ($this->read)(DashboardOrderScope::Reception)));
    }

    private function action(DashboardReceptionStage $stage): ActionKind
    {
        return match ($stage) {
            DashboardReceptionStage::Check => ActionKind::CheckDeliveryAndAllocations,
            DashboardReceptionStage::ResolveAllocations => ActionKind::ResolveAllocations,
            DashboardReceptionStage::Distribute => ActionKind::MarkDistributed,
        };
    }
}
