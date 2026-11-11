<?php

namespace Guixa\Dashboard\Infrastructure\Sales;

use Guixa\Dashboard\Domain\BasketWorkRepository;
use Guixa\Dashboard\Domain\ValueObject\BasketWork;
use Guixa\Sales\Application\DashboardBasketWorkData;
use Guixa\Sales\Application\ReadDashboardBasketWork;

final class SalesBasketWork implements BasketWorkRepository
{
    public function __construct(private readonly ReadDashboardBasketWork $read) {}

    public function memberCollection(string $householdId): array
    {
        return $this->mapped(($this->read)($householdId));
    }

    public function staffCollection(): array
    {
        return $this->mapped(($this->read)());
    }

    /**
     * @param  list<DashboardBasketWorkData>  $work
     * @return list<BasketWork>
     */
    private function mapped(array $work): array
    {
        return array_map(fn (DashboardBasketWorkData $item) => new BasketWork($item->basketId, $item->cycleId, $item->pickupDate), $work);
    }
}
