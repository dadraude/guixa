<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\ProducerRow;
use App\Http\Requests\Manage\StockProductRequest;
use App\Http\StockLevelRow;
use App\Http\StockProductRow;
use Guixa\Catalog\Application\CreateStockProduct;
use Guixa\Catalog\Application\DeactivateStockProduct;
use Guixa\Catalog\Application\DeleteUnusedProduct;
use Guixa\Catalog\Application\GetProducer;
use Guixa\Catalog\Application\IsProductUsed;
use Guixa\Catalog\Application\ListStockProducts;
use Guixa\Catalog\Application\ReactivateStockProduct;
use Guixa\Catalog\Application\UpdateStockProduct;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\ListStock;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockProductController extends Controller
{
    public function index(Actor $actor, ListStock $list): Response
    {
        return Inertia::render('Manage/Stock/Index', [
            'products' => array_map(StockLevelRow::from(...), $list($actor)),
        ]);
    }

    public function create(string $producer, GetProducer $getProducer): Response
    {
        $record = $getProducer($producer);
        abort_if($record === null, 404);

        return Inertia::render('Manage/Stock/Create', ['producer' => ProducerRow::from($record, null)]);
    }

    public function store(string $producer, StockProductRequest $request, Actor $actor, CreateStockProduct $create): RedirectResponse
    {
        $create(
            $actor,
            $producer,
            $request->string('name')->toString(),
            $request->string('unit')->toString(),
            $request->priceCents(),
            $request->boolean('variable'),
            $request->containersPerUnit(),
        );

        return to_route('manage.stock.index');
    }

    public function edit(string $producer, string $stockProduct, ListStockProducts $list, IsProductUsed $used): Response
    {
        $product = collect($list())->firstWhere('id', $stockProduct);
        abort_if($product === null || $product->producerId !== $producer, 404);

        return Inertia::render('Manage/Stock/Edit', ['product' => StockProductRow::from($product), 'used' => $used($stockProduct)]);
    }

    public function update(string $producer, string $stockProduct, StockProductRequest $request, Actor $actor, UpdateStockProduct $update): RedirectResponse
    {
        $update(
            $actor,
            $producer,
            $stockProduct,
            $request->string('name')->toString(),
            $request->string('unit')->toString(),
            $request->priceCents(),
            $request->boolean('variable'),
            $request->containersPerUnit(),
        );

        return to_route('manage.stock.index');
    }

    public function destroy(string $producer, string $stockProduct, Actor $actor, DeactivateStockProduct $deactivate): RedirectResponse
    {
        $deactivate($actor, $producer, $stockProduct);

        return to_route('manage.stock.index');
    }

    public function deleteUnused(string $producer, string $stockProduct, Actor $actor, DeleteUnusedProduct $delete, ListStockProducts $list): RedirectResponse
    {
        $product = collect($list())->firstWhere('id', $stockProduct);
        abort_if($product === null || $product->producerId !== $producer, 404);
        $delete($actor, $stockProduct);

        return to_route('catalogue.producers.products.index', ['producer' => $producer]);
    }

    public function reactivate(string $producer, string $stockProduct, Actor $actor, ReactivateStockProduct $reactivate): RedirectResponse
    {
        $reactivate($actor, $producer, $stockProduct);

        return to_route('manage.stock.index');
    }
}
