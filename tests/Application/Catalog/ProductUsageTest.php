<?php

use Guixa\Catalog\Infrastructure\Ordering\RetainedProductUsage;
use Guixa\Ordering\Application\CheckProductUsage as CheckOrderingProductUsage;
use Guixa\Sales\Application\HasProductHistory;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Application\CheckProductUsage as CheckStockProductUsage;
use Guixa\Stock\Domain\StockMovement;
use Tests\Support\InMemory\InMemoryOrderRepository;
use Tests\Support\InMemory\InMemoryReceivedTotalRepository;
use Tests\Support\InMemory\InMemoryStockMovementRepository;

it('derives stock use from movements without relying on quantity on hand (R1b, R25a, S68, S72)', function () {
    $movements = new InMemoryStockMovementRepository;
    $baskets = new class implements BasketRepository
    {
        public array $used = [];

        public function nextId(): string
        {
            return 'unused';
        }

        public function save(Basket $basket): void {}

        public function get(string $id): ?Basket
        {
            return null;
        }

        public function getBySourceOrder(string $orderId): ?Basket
        {
            return null;
        }

        public function getOpenLateBySourceOrder(string $orderId): ?Basket
        {
            return null;
        }

        public function weeklyOfCycle(string $cycleId): array
        {
            return [];
        }

        public function lock(string $id): ?Basket
        {
            return null;
        }

        public function list(): array
        {
            return [];
        }

        public function getForHousehold(string $id, string $householdId): ?Basket
        {
            $basket = $this->get($id);

            return $basket?->householdId === $householdId ? $basket : null;
        }

        public function listForHousehold(string $householdId): array
        {
            return array_values(array_filter($this->list(),
                fn (Basket $basket) => $basket->householdId === $householdId));
        }

        public function hasProductHistory(string $productId): bool
        {
            return isset($this->used[$productId]);
        }
    };
    $usage = new RetainedProductUsage(
        new CheckOrderingProductUsage(new InMemoryOrderRepository, new InMemoryReceivedTotalRepository),
        new CheckStockProductUsage($movements),
        new HasProductHistory($baskets),
    );
    $productId = '00000000-0000-7000-8000-0000000000f3';
    expect($usage->hasAny($productId))->toBeFalse()
        ->and($usage->hasStockUse($productId))->toBeFalse();
    $movements->add(StockMovement::entry($movements->nextId(), $productId, Quantity::fromString('1'), new DateTimeImmutable('2026-10-22T10:00:00+00:00'), '00000000-0000-7000-8000-0000000000a1', null));
    expect($usage->hasAny($productId))->toBeTrue()
        ->and($usage->hasStockUse($productId))->toBeTrue();

    $salesOnlyProduct = '00000000-0000-7000-8000-0000000000f4';
    $baskets->used[$salesOnlyProduct] = true;
    expect($usage->hasAny($salesOnlyProduct))->toBeTrue()
        ->and($usage->hasStockUse($salesOnlyProduct))->toBeTrue();
});
