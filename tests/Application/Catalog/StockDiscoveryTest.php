<?php

use Guixa\Catalog\Application\CreateStockProduct;
use Guixa\Catalog\Application\DeactivateStockProduct;
use Guixa\Catalog\Application\ListProducerStockProducts;
use Guixa\Catalog\Application\ListStockOfferProducts;
use Guixa\Catalog\Domain\StockAvailability;
use Guixa\Shared\Application\Actor;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\Catalog\CatalogWorld;

it('shows only active stock to members, with inactive producers and separate weekly identity (S66 S73)', function () {
    $world = new CatalogWorld;
    $beekeeper = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $oilProducer = ($world->createProducer)($world->manager, 'Oil maker', 3, '20:00');
    $create = new CreateStockProduct($world->products, $world->producers);
    $honey = $create($world->manager, $beekeeper, 'Honey', 'jar', 650, false, null);
    $oil = $create($world->manager, $oilProducer, 'Oil', 'bottle', 450, false, null);
    $jam = $create($world->manager, $beekeeper, 'Jam', 'jar', 300, false, null);
    ($world->createProduct)($world->producerActor($beekeeper), $beekeeper, 'Honey', 'jar', 650, false, null);
    (new DeactivateStockProduct($world->products, $world->transactions))($world->manager, $beekeeper, $jam);
    ($world->deactivateProducer)($world->manager, $beekeeper);

    $listed = (new ListStockOfferProducts($world->products, $world->producers))();

    expect(array_column($listed, 'id'))->toBe([$honey, $oil])
        ->and(array_column($listed, 'producerName'))->toBe(['Beekeeper', 'Oil maker'])
        ->and(array_column($listed, 'priceCents'))->toBe([650, 450]);
});

it('shows own active and inactive stock in one quantity batch and rejects another producer (S74)', function () {
    $world = new CatalogWorld;
    $beekeeper = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $oilProducer = ($world->createProducer)($world->manager, 'Oil maker', 3, '20:00');
    $create = new CreateStockProduct($world->products, $world->producers);
    $honey = $create($world->manager, $beekeeper, 'Honey', 'jar', 650, false, null);
    $jam = $create($world->manager, $beekeeper, 'Jam', 'jar', 300, false, null);
    $create($world->manager, $oilProducer, 'Oil', 'bottle', 450, false, null);
    (new DeactivateStockProduct($world->products, $world->transactions))($world->manager, $beekeeper, $jam);
    $availability = new class implements StockAvailability
    {
        public array $calls = [];

        public function forProducts(array $productIds): array
        {
            $this->calls[] = $productIds;

            return [$productIds[0] => '5.000'];
        }
    };
    $list = new ListProducerStockProducts($world->products, $availability);

    $listed = $list($world->producerActor($beekeeper), $beekeeper);

    expect(array_column($listed, 'id'))->toBe([$honey, $jam])
        ->and(array_column($listed, 'available'))->toBe(['5.000', '0.000'])
        ->and(array_column($listed, 'active'))->toBe([true, false])
        ->and($availability->calls)->toBe([[$honey, $jam]])
        ->and(fn () => $list($world->producerActor($beekeeper), $oilProducer))->toThrow(AuthorizationException::class)
        ->and(fn () => $list(Actor::memberOf('member', 'household'), $beekeeper))->toThrow(AuthorizationException::class);
});
