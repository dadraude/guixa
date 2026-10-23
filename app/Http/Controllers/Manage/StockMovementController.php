<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\StockAdjustmentRequest;
use App\Http\Requests\Manage\StockEntryRequest;
use App\Http\StockLevelRow;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\ListStock;
use Guixa\Stock\Application\RecordStockAdjustment;
use Guixa\Stock\Application\RecordStockEntry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    public function show(string $stockProduct, Actor $actor, ListStock $list): Response
    {
        $level = collect($list($actor))->firstWhere('id', $stockProduct);
        abort_if($level === null, 404);

        return Inertia::render('Manage/Stock/Movements', ['product' => StockLevelRow::from($level)]);
    }

    public function storeEntry(string $stockProduct, StockEntryRequest $request, Actor $actor, RecordStockEntry $record): RedirectResponse
    {
        $record($actor, $stockProduct, $request->quantity(), $request->note());

        return to_route('manage.stock.movements.show', $stockProduct);
    }

    public function storeAdjustment(string $stockProduct, StockAdjustmentRequest $request, Actor $actor, RecordStockAdjustment $record): RedirectResponse
    {
        $record($actor, $stockProduct, $request->quantity(), $request->string('note')->toString(), null);

        return to_route('manage.stock.movements.show', $stockProduct);
    }
}
