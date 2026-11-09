<?php

namespace App\Http\Controllers\Producer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Stock\ReadsStockHistory;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\ListStockMovements;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StockMovementController extends Controller
{
    use ReadsStockHistory;

    public function show(string $stockProduct, Request $request, Actor $actor, ListStockMovements $list): Response
    {
        $page = $this->history($request, $actor, $list, $stockProduct, asProducer: true);

        return Inertia::render('Producer/Stock/Movements', [
            'product' => ['name' => $page->productName, 'unit' => $page->unit, 'available' => $page->available],
            'history' => $this->historyRow($request, $page),
        ]);
    }
}
