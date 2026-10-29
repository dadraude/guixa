<?php

/* Runs one public catalogue use case in another process for a lock race. */

use Guixa\Catalog\Application\ChangeProductClassification;
use Guixa\Catalog\Application\DeactivateProduct;
use Guixa\Catalog\Application\DeactivateStockProduct;
use Guixa\Catalog\Application\DeleteUnusedProduct;
use Guixa\Catalog\Application\ReactivateProduct;
use Guixa\Catalog\Application\ReactivateStockProduct;
use Guixa\Catalog\Application\UpdateProduct;
use Guixa\Catalog\Application\UpdateStockProduct;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Application\Actor;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    [$script, $action, $id, $producerId] = $argv;
    $manager = Actor::manager('00000000-0000-7000-8000-0000000000a1');
    $product = $app->make(ProductRepository::class)->get($id);
    match ($action) {
        'classify-stock' => $app->make(ChangeProductClassification::class)($manager, $id, true),
        'classify-weekly' => $app->make(ChangeProductClassification::class)($manager, $id, false),
        'delete-stock' => $app->make(DeleteUnusedProduct::class)($manager, $id),
        'delete-weekly' => $app->make(DeleteUnusedProduct::class)(Actor::producer('00000000-0000-7000-8000-0000000000a2', $producerId), $id),
        'deactivate-weekly' => $app->make(DeactivateProduct::class)(Actor::producer('00000000-0000-7000-8000-0000000000a2', $producerId), $id),
        'reactivate-weekly' => $app->make(ReactivateProduct::class)(Actor::producer('00000000-0000-7000-8000-0000000000a2', $producerId), $id),
        'deactivate-stock' => $app->make(DeactivateStockProduct::class)($manager, $producerId, $id),
        'reactivate-stock' => $app->make(ReactivateStockProduct::class)($manager, $producerId, $id),
        'stock-unit' => $app->make(UpdateStockProduct::class)($manager, $producerId, $id, $product->name(), 'kg', $product->price()->cents, $product->isVariable(), $product->containersPerUnit()?->toString()),
        'weekly-unit' => $app->make(UpdateProduct::class)(Actor::producer('00000000-0000-7000-8000-0000000000a2', $producerId), $id, $product->name(), 'kg', $product->price()->cents, $product->isVariable(), $product->containersPerUnit()?->toString()),
    };
    echo 'done';
} catch (Throwable $e) {
    echo (new ReflectionClass($e))->getShortName();
}
