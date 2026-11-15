<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Stock\ReadsStockHistory;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\ListStockMovements;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only movement history of any stock product, without the baskets behind sales (R4, D5). */
final class StockMovementController extends Controller
{
    use ReadsStockHistory;

    public function show(string $stockProduct, Request $request, Actor $actor, ListStockMovements $list): Response
    {
        $page = $this->history($request, $actor, $list, $stockProduct);

        return Inertia::render('Catalogue/Stock/Movements', [
            'product' => ['name' => $page->productName, 'unit' => $page->unit, 'available' => $page->available],
            'history' => $this->historyRow($request, $page),
        ]);
    }
}
