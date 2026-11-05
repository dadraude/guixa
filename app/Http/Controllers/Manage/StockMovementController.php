<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\CycleOptionRow;
use App\Http\Requests\Manage\StockAdjustmentRequest;
use App\Http\Requests\Manage\StockEntryRequest;
use App\Http\StockLevelRow;
use Guixa\Ordering\Application\ListCycles;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\ListStock;
use Guixa\Stock\Application\RecordStockAdjustment;
use Guixa\Stock\Application\RecordStockEntry;
use Guixa\Stock\Application\SetStockAvailable;
use Guixa\Stock\Domain\Error\StockProductNotFound;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    public function show(string $stockProduct, Actor $actor, ListStock $list, ListCycles $listCycles): Response
    {
        $level = collect($list($actor))->firstWhere('id', $stockProduct);
        abort_if($level === null, 404);

        return Inertia::render('Manage/Stock/Movements', [
            'product' => StockLevelRow::from($level),
            'cycles' => array_map(CycleOptionRow::from(...), $listCycles($actor)),
        ]);
    }

    public function storeEntry(string $stockProduct, StockEntryRequest $request, Actor $actor, RecordStockEntry $record): RedirectResponse
    {
        $record($actor, $stockProduct, $request->quantity(), $request->note());

        return to_route('manage.stock.movements.show', $stockProduct);
    }

    public function storeAdjustment(string $stockProduct, StockAdjustmentRequest $request, Actor $actor, RecordStockAdjustment $record): RedirectResponse
    {
        $record($actor, $stockProduct, $request->quantity(), $request->string('note')->toString(), $request->cycleId());

        return to_route('manage.stock.movements.show', $stockProduct);
    }

    public function setAvailable(string $stockProduct, Request $request, Actor $actor, SetStockAvailable $setAvailable): RedirectResponse
    {
        $data = $request->validate([
            'available' => ['required', 'string', 'regex:/^\d{1,7}(?:[.,]\d{1,3})?$/'],
        ]);

        try {
            $setAvailable($actor, $stockProduct, str_replace(',', '.', $data['available']), __('Inventory count'));
        } catch (StockProductNotFound) {
            abort(404);
        }

        return to_route('manage.stock.index');
    }
}
