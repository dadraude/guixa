<?php

use Guixa\Catalog\Application\ChangeProductClassification;
use Guixa\Catalog\Application\CreateStockProduct;
use Guixa\Catalog\Application\DeactivateStockProduct;
use Guixa\Catalog\Application\DeleteUnusedProduct;
use Guixa\Catalog\Application\IsProductUsed;
use Guixa\Catalog\Application\ListProductsOfProducer;
use Guixa\Catalog\Application\ListStockProducts;
use Guixa\Catalog\Application\ReactivateStockProduct;
use Guixa\Catalog\Application\UpdateStockProduct;
use Guixa\Catalog\Domain\Error\InvalidContainersPerUnit;
use Guixa\Catalog\Domain\Error\InvalidPrice;
use Guixa\Catalog\Domain\Error\ProducerNotFound;
use Guixa\Catalog\Domain\Error\ProductHasStockUse;
use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\Error\ProductUsed;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\Catalog\CatalogWorld;

it('creates a distinct stock record from a supplying producer (R1a, S65, S66, S71)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Oil producer', 3, '20:00');
    $weeklyId = ($world->createProduct)($world->producerActor($producerId), $producerId, 'Oil', 'bottle', 650, false, null);

    $stockId = (new CreateStockProduct($world->products, $world->producers))($world->manager, $producerId, 'Oil', 'bottle', 650, false, null);

    expect($stockId)->not->toBe($weeklyId)
        ->and($world->products->get($stockId)->producerId)->toBe($producerId)
        ->and($world->products->get($stockId)->isStock())->toBeTrue()
        ->and($world->products->get($weeklyId)->isStock())->toBeFalse();
});

it('lets a manager administer only stock records while keeping producer identity fixed (R1a, R25, S65, S71)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $weeklyId = ($world->createProduct)($world->producerActor($producerId), $producerId, 'Honey', 'jar', 500, false, null);
    $stockId = (new CreateStockProduct($world->products, $world->producers))($world->manager, $producerId, 'Honey', 'jar', 650, false, null);
    $update = new UpdateStockProduct($world->products, $world->usage, $world->transactions);

    $update($world->manager, $producerId, $stockId, 'Raw honey', 'kg', 1200, true, '2.000');

    $listed = (new ListStockProducts($world->products))();
    expect(array_column($listed, 'id'))->toBe([$stockId])
        ->and($listed[0]->producerId)->toBe($producerId)
        ->and($listed[0]->name)->toBe('Raw honey')
        ->and($listed[0]->unit)->toBe('kg')
        ->and($listed[0]->priceCents)->toBe(1200)
        ->and($listed[0]->variable)->toBeTrue()
        ->and($listed[0]->containersPerUnit)->toBe('2.000')
        ->and(fn () => $update($world->manager, $producerId, $weeklyId, 'Wrong', 'jar', 500, false, null))->toThrow(ProductNotFound::class)
        ->and(fn () => $update($world->producerActor($producerId), $producerId, $stockId, 'Wrong', 'jar', 500, false, null))->toThrow(AuthorizationException::class);
});

it('rejects stock creation without a supplying producer or manager (R1a, S65, S71)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $create = new CreateStockProduct($world->products, $world->producers);

    expect(fn () => $create($world->manager, 'missing', 'Honey', 'jar', 650, false, null))->toThrow(ProducerNotFound::class)
        ->and(fn () => $create($world->producerActor($producerId), $producerId, 'Honey', 'jar', 650, false, null))->toThrow(AuthorizationException::class)
        ->and($world->products->listByProducer($producerId))->toBe([]);
});

it('excludes stock from weekly ordering and reception even for an active producer (R3, S66, S77)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Oil producer', 3, '20:00');
    $weeklyId = ($world->createProduct)($world->producerActor($producerId), $producerId, 'Oil', 'bottle', 650, false, null);
    $stockId = (new CreateStockProduct($world->products, $world->producers))($world->manager, $producerId, 'Oil', 'bottle', 650, false, null);

    expect(($world->getOrderableProduct)($stockId))->toBeNull()
        ->and(array_column(($world->listOrderableProducts)($producerId), 'id'))->toBe([$weeklyId])
        ->and(array_column(($world->listProducerProductsForReception)($producerId), 'id'))->toBe([$weeklyId]);
});

it('keeps stock hidden from producer administration and rejects forged edits (R2, R25, S71)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $actor = $world->producerActor($producerId);
    $stockId = (new CreateStockProduct($world->products, $world->producers))($world->manager, $producerId, 'Honey', 'jar', 650, false, null);

    expect(($world->listProducerProducts)($actor, $producerId))->toBe([])
        ->and(fn () => ($world->updateProduct)($actor, $stockId, 'Changed', 'jar', 650, false, null))->toThrow(AuthorizationException::class)
        ->and(fn () => ($world->deactivateProduct)($actor, $stockId))->toThrow(AuthorizationException::class)
        ->and(fn () => ($world->reactivateProduct)($actor, $stockId))->toThrow(AuthorizationException::class)
        ->and($world->products->get($stockId)->name())->toBe('Honey');
});

it('returns consecutive weekly product keys when stock sorts before and between them (R2, S66)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $actor = $world->producerActor($producerId);
    $createStock = new CreateStockProduct($world->products, $world->producers);
    $createStock($world->manager, $producerId, 'Apple stock', 'jar', 650, false, null);
    ($world->createProduct)($actor, $producerId, 'Banana weekly', 'jar', 500, false, null);
    $createStock($world->manager, $producerId, 'Cherry stock', 'jar', 650, false, null);
    ($world->createProduct)($actor, $producerId, 'Date weekly', 'jar', 500, false, null);

    $listed = ($world->listProducerProducts)($actor, $producerId);

    expect(array_keys($listed))->toBe([0, 1])
        ->and(array_column($listed, 'name'))->toBe(['Banana weekly', 'Date weekly'])
        ->and(json_encode($listed))->toStartWith('[');
});

it('lets only a manager deactivate and reactivate stock, including an inactive producer (R25, S71)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $stockId = (new CreateStockProduct($world->products, $world->producers))($world->manager, $producerId, 'Honey', 'jar', 650, false, null);
    ($world->deactivateProducer)($world->manager, $producerId);
    $deactivate = new DeactivateStockProduct($world->products, $world->transactions);
    $reactivate = new ReactivateStockProduct($world->products, $world->transactions);

    $deactivate($world->manager, $producerId, $stockId);
    expect($world->products->get($stockId)->isActive())->toBeFalse()
        ->and(fn () => $reactivate($world->producerActor($producerId), $producerId, $stockId))->toThrow(AuthorizationException::class);

    $reactivate($world->manager, $producerId, $stockId);
    expect($world->products->get($stockId)->isActive())->toBeTrue();
});

it('rejects invalid stock price and containers without creating a product (R25)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $create = new CreateStockProduct($world->products, $world->producers);

    expect(fn () => $create($world->manager, $producerId, 'Honey', 'jar', -1, false, null))->toThrow(InvalidPrice::class)
        ->and(fn () => $create($world->manager, $producerId, 'Honey', 'jar', 650, false, '0'))->toThrow(InvalidContainersPerUnit::class)
        ->and($world->products->listStock())->toBe([]);
});

it('lets only a manager reclassify an unused product and transfers administration (R1b, S67)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $weeklyId = ($world->createProduct)($world->producerActor($producerId), $producerId, 'Honey', 'jar', 500, false, null);
    $change = new ChangeProductClassification($world->products, $world->usage, $world->transactions);

    expect(fn () => $change($world->producerActor($producerId), $weeklyId, true))->toThrow(AuthorizationException::class);
    $change($world->manager, $weeklyId, true);
    expect($world->products->get($weeklyId)->isStock())->toBeTrue()
        ->and(fn () => ($world->updateProduct)($world->producerActor($producerId), $weeklyId, 'Changed', 'jar', 500, false, null))->toThrow(AuthorizationException::class);
    $change($world->manager, $weeklyId, false);
    expect($world->products->get($weeklyId)->isStock())->toBeFalse();
});

it('lets Catalogue reclassify an unused product, as the manager does, and refuses a Finance account (R4, 001 R1b, S6)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $weeklyId = ($world->createProduct)($world->producerActor($producerId), $producerId, 'Honey', 'jar', 500, false, null);
    $change = new ChangeProductClassification($world->products, $world->usage, $world->transactions);

    $change($world->catalogueActor(), $weeklyId, true);

    expect($world->products->get($weeklyId)->isStock())->toBeTrue()
        ->and(fn () => $change(new Actor('00000000-0000-7000-8000-0000000000ff', [Role::Finance]), $weeklyId, false))->toThrow(AuthorizationException::class);
});

it('deletes only unused products through their current administrator (R1c, S70)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $producer = $world->producerActor($producerId);
    $weeklyId = ($world->createProduct)($producer, $producerId, 'Bread', 'loaf', 350, false, null);
    $stockId = (new CreateStockProduct($world->products, $world->producers))($world->manager, $producerId, 'Honey', 'jar', 650, false, null);
    $delete = new DeleteUnusedProduct($world->products, $world->usage, $world->transactions);

    expect(fn () => $delete($world->producerActor('another-producer'), $weeklyId))->toThrow(AuthorizationException::class)
        ->and(fn () => $delete(new Actor('00000000-0000-7000-8000-0000000000ff', [Role::Finance]), $stockId))->toThrow(AuthorizationException::class)
        ->and(fn () => $delete($producer, $stockId))->toThrow(AuthorizationException::class);
    $delete($producer, $weeklyId);
    $delete($world->catalogueActor(), $stockId);
    expect($world->products->get($weeklyId))->toBeNull()
        ->and($world->products->get($stockId))->toBeNull();
});

it('retained use prevents mode change and deletion but permits deactivation (R1b, R1c, S68)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $producer = $world->producerActor($producerId);
    $weeklyId = ($world->createProduct)($producer, $producerId, 'Bread', 'loaf', 350, false, null);
    $world->usage->any[$weeklyId] = true;
    $change = new ChangeProductClassification($world->products, $world->usage, $world->transactions);
    $delete = new DeleteUnusedProduct($world->products, $world->usage, $world->transactions);

    expect(fn () => $change($world->manager, $weeklyId, true))->toThrow(ProductUsed::class)
        ->and(fn () => $delete($producer, $weeklyId))->toThrow(ProductUsed::class);
    ($world->deactivateProduct)($producer, $weeklyId);
    expect($world->products->get($weeklyId)->isActive())->toBeFalse();
});

it('keeps stock unit and variable flag fixed after historical stock use (R25a, S72)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $stockId = (new CreateStockProduct($world->products, $world->producers))($world->manager, $producerId, 'Honey', 'jar', 650, false, null);
    $world->usage->stock[$stockId] = true;
    $update = new UpdateStockProduct($world->products, $world->usage, $world->transactions);

    expect(fn () => $update($world->manager, $producerId, $stockId, 'Honey', 'kg', 650, false, null))->toThrow(ProductHasStockUse::class)
        ->and(fn () => $update($world->manager, $producerId, $stockId, 'Honey', 'jar', 650, true, null))->toThrow(ProductHasStockUse::class);
    $update($world->manager, $producerId, $stockId, 'Raw honey', ' jar ', 700, false, '2');
    expect($world->products->get($stockId)->name())->toBe('Raw honey')
        ->and($world->products->get($stockId)->unit())->toBe('jar');
});

it('shows a manager both modes and whether each product has any retained use (R1b, S67, S68)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $weeklyId = ($world->createProduct)($world->producerActor($producerId), $producerId, 'Bread', 'loaf', 350, false, null);
    $stockId = (new CreateStockProduct($world->products, $world->producers))($world->manager, $producerId, 'Honey', 'jar', 650, false, null);
    $world->usage->any[$weeklyId] = true;
    $list = new ListProductsOfProducer($world->products, $world->usage);

    $rows = $list($world->manager, $producerId);
    expect(collect($rows)->firstWhere('id', $weeklyId)->stock)->toBeFalse()
        ->and(collect($rows)->firstWhere('id', $weeklyId)->used)->toBeTrue()
        ->and(collect($rows)->firstWhere('id', $stockId)->stock)->toBeTrue()
        ->and(collect($rows)->firstWhere('id', $stockId)->used)->toBeFalse()
        ->and(fn () => $list($world->producerActor($producerId), $producerId))->toThrow(AuthorizationException::class);
});

it('answers whether a product has any retained use for administration controls (R1b)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $id = ($world->createProduct)($world->producerActor($producerId), $producerId, 'Bread', 'loaf', 350, false, null);
    $query = new IsProductUsed($world->usage);
    expect($query($id))->toBeFalse();
    $world->usage->any[$id] = true;
    expect($query($id))->toBeTrue();
});

it('lets Catalogue, and a producer account holding it, administer stock products of any producer (R4, R25b, S6)', function (Actor $actor) {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $create = new CreateStockProduct($world->products, $world->producers);
    $update = new UpdateStockProduct($world->products, $world->usage, $world->transactions);

    $stockId = $create($actor, $producerId, 'Honey', 'jar', 650, false, null);
    $update($actor, $producerId, $stockId, 'Raw honey', 'jar', 700, false, null);
    (new DeactivateStockProduct($world->products, $world->transactions))($actor, $producerId, $stockId);
    expect($world->products->get($stockId)->isActive())->toBeFalse();
    (new ReactivateStockProduct($world->products, $world->transactions))($actor, $producerId, $stockId);

    expect($world->products->get($stockId)->isActive())->toBeTrue()
        ->and($world->products->get($stockId)->name())->toBe('Raw honey');
})->with([
    'catalogue' => fn () => (new CatalogWorld)->catalogueActor(),
    'producer with catalogue' => fn () => new Actor('00000000-0000-7000-8000-0000000000dd', [Role::Producer, Role::Catalogue], producerId: 'any-producer'),
]);

it('refuses stock administration to a producer account without Catalogue, Finance and receivers (R4, 001 R25b)', function (Actor $actor) {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $stockId = (new CreateStockProduct($world->products, $world->producers))($world->manager, $producerId, 'Honey', 'jar', 650, false, null);

    expect(fn () => (new CreateStockProduct($world->products, $world->producers))($actor, $producerId, 'Jam', 'jar', 300, false, null))->toThrow(AuthorizationException::class)
        ->and(fn () => (new UpdateStockProduct($world->products, $world->usage, $world->transactions))($actor, $producerId, $stockId, 'Honey', 'jar', 650, false, null))->toThrow(AuthorizationException::class)
        ->and(fn () => (new DeactivateStockProduct($world->products, $world->transactions))($actor, $producerId, $stockId))->toThrow(AuthorizationException::class)
        ->and(fn () => (new ReactivateStockProduct($world->products, $world->transactions))($actor, $producerId, $stockId))->toThrow(AuthorizationException::class);
})->with([
    'own producer' => fn () => Actor::producer('00000000-0000-7000-8000-0000000000aa', 'any-producer'),
    'finance' => fn () => new Actor('00000000-0000-7000-8000-0000000000ff', [Role::Finance]),
    'receiver' => fn () => new Actor('00000000-0000-7000-8000-0000000000ee', [Role::Receiver]),
]);

it('shows Catalogue the stock classification and edit flags on a producer\'s products (R4, S6)', function () {
    $world = new CatalogWorld;
    $producerId = ($world->createProducer)($world->manager, 'Beekeeper', 3, '20:00');
    $stockId = (new CreateStockProduct($world->products, $world->producers))($world->manager, $producerId, 'Honey', 'jar', 650, false, null);

    $row = collect((new ListProductsOfProducer($world->products, $world->usage))($world->catalogueActor(), $producerId))->firstWhere('id', $stockId);

    expect($row->canEdit)->toBeTrue()
        ->and($row->canChangeClassification)->toBeTrue()
        ->and($row->canDelete)->toBeTrue();
});
