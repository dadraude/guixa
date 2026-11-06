<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Stock\ReadsStockHistory;
use App\Http\StockLevelRow;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\ListStock;
use Guixa\Stock\Application\ListStockMovements;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StockController extends Controller
{
    use ReadsStockHistory;

    public function index(Actor $actor, ListStock $list): Response
    {
        return Inertia::render('Finance/Stock/Index', [
            'products' => array_map(StockLevelRow::from(...), $list($actor)),
        ]);
    }

    public function movements(string $stockProduct, Request $request, Actor $actor, ListStockMovements $list): Response
    {
        $page = $this->history($request, $actor, $list, $stockProduct);

        return Inertia::render('Finance/Stock/Movements', [
            'product' => ['name' => $page->productName, 'unit' => $page->unit, 'available' => $page->available],
            'history' => $this->historyRow($request, $page),
        ]);
    }
}
