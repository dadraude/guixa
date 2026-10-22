<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\StockProductRequest;
use App\Http\StockProductRow;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\CreateStockProduct;
use Guixa\Stock\Application\DeactivateStockProduct;
use Guixa\Stock\Application\ListStockProducts;
use Guixa\Stock\Application\UpdateStockProduct;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockProductController extends Controller
{
    public function index(Actor $actor, ListStockProducts $list): Response
    {
        return Inertia::render('Manage/Stock/Index', [
            'products' => array_map(StockProductRow::from(...), $list($actor)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Manage/Stock/Create');
    }

    public function store(StockProductRequest $request, Actor $actor, CreateStockProduct $create): RedirectResponse
    {
        $create(
            $actor,
            $request->string('name')->toString(),
            $request->string('unit')->toString(),
            $request->priceCents(),
            $request->boolean('variable'),
            $request->containersPerUnit(),
        );

        return to_route('manage.stock.index');
    }

    public function edit(string $stockProduct, Actor $actor, ListStockProducts $list): Response
    {
        $product = collect($list($actor))->firstWhere('id', $stockProduct);
        abort_if($product === null, 404);

        return Inertia::render('Manage/Stock/Edit', ['product' => StockProductRow::from($product)]);
    }

    public function update(string $stockProduct, StockProductRequest $request, Actor $actor, UpdateStockProduct $update): RedirectResponse
    {
        $update(
            $actor,
            $stockProduct,
            $request->string('name')->toString(),
            $request->string('unit')->toString(),
            $request->priceCents(),
            $request->boolean('variable'),
            $request->containersPerUnit(),
        );

        return to_route('manage.stock.index');
    }

    public function destroy(string $stockProduct, Actor $actor, DeactivateStockProduct $deactivate): RedirectResponse
    {
        $deactivate($actor, $stockProduct);

        return to_route('manage.stock.index');
    }
}
