<?php

use Guixa\Catalog\Infrastructure\Ordering\RetainedProductUsage;
use Guixa\Ordering\Application\CheckProductUsage as CheckOrderingProductUsage;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Application\CheckProductUsage as CheckStockProductUsage;
use Guixa\Stock\Domain\StockMovement;
use Tests\Support\InMemory\InMemoryOrderRepository;
use Tests\Support\InMemory\InMemoryReceivedTotalRepository;
use Tests\Support\InMemory\InMemoryStockMovementRepository;

it('derives stock use from movements without relying on quantity on hand (R1b, R25a, S68, S72)', function () {
    $movements = new InMemoryStockMovementRepository;
    $usage = new RetainedProductUsage(
        new CheckOrderingProductUsage(new InMemoryOrderRepository, new InMemoryReceivedTotalRepository),
        new CheckStockProductUsage($movements),
    );
    $productId = '00000000-0000-7000-8000-0000000000f3';
    expect($usage->hasAny($productId))->toBeFalse()
        ->and($usage->hasStockUse($productId))->toBeFalse();
    $movements->add(StockMovement::entry($movements->nextId(), $productId, Quantity::fromString('1'), new DateTimeImmutable('2026-10-22T10:00:00+00:00'), '00000000-0000-7000-8000-0000000000a1', null));
    expect($usage->hasAny($productId))->toBeTrue()
        ->and($usage->hasStockUse($productId))->toBeTrue();
});
