<?php

namespace App\Http\Controllers\Stock;

use App\Http\StockMovementPageRow;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\ListStockMovements;
use Guixa\Stock\Application\StockMovementPage;
use Guixa\Stock\Domain\Error\StockProductNotFound;
use Illuminate\Http\Request;

/** Shared by the three pages that show a stock product's history (R28a). */
trait ReadsStockHistory
{
    private function history(Request $request, Actor $actor, ListStockMovements $list, string $stockProduct): StockMovementPage
    {
        try {
            return $list($actor, $stockProduct, max(1, $request->integer('page', 1)));
        } catch (StockProductNotFound) {
            abort(404);
        }
    }

    private function historyRow(Request $request, StockMovementPage $page): StockMovementPageRow
    {
        return StockMovementPageRow::from($page, ListStockMovements::PER_PAGE, max(1, $request->integer('page', 1)), $request->url());
    }
}
