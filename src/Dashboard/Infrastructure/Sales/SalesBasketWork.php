<?php

namespace Guixa\Dashboard\Infrastructure\Sales;

use Guixa\Dashboard\Domain\ActionKind;
use Guixa\Dashboard\Domain\BasketWorkRepository;
use Guixa\Dashboard\Domain\ValueObject\BasketWork;
use Guixa\Dashboard\Infrastructure\TranslatesReadFailures;
use Guixa\Sales\Application\DashboardBasketScope;
use Guixa\Sales\Application\DashboardBasketStage;
use Guixa\Sales\Application\DashboardBasketWorkData;
use Guixa\Sales\Application\ReadDashboardBasketWork;

final class SalesBasketWork implements BasketWorkRepository
{
    use TranslatesReadFailures;

    public function __construct(private readonly ReadDashboardBasketWork $read) {}

    public function memberCollection(string $householdId): array
    {
        return $this->mapped($this->guarded('baskets', fn () => ($this->read)($householdId)));
    }

    public function staffCollection(): array
    {
        return $this->mapped($this->guarded('baskets', fn () => ($this->read)()));
    }

    public function staffDrafts(): array
    {
        return $this->mapped($this->guarded('baskets', fn () => ($this->read)(null, DashboardBasketScope::Drafts)));
    }

    /**
     * @param  list<DashboardBasketWorkData>  $work
     * @return list<BasketWork>
     */
    private function mapped(array $work): array
    {
        return array_map(fn (DashboardBasketWorkData $item) => new BasketWork($item->basketId, $item->cycleId, $item->pickupDate, $this->actionOf($item->stage)), $work);
    }

    private function actionOf(DashboardBasketStage $stage): ActionKind
    {
        return match ($stage) {
            DashboardBasketStage::Prepare => ActionKind::PrepareBasket,
            DashboardBasketStage::Validate => ActionKind::ValidateBasket,
            DashboardBasketStage::Collect => ActionKind::CollectBasket,
        };
    }
}
