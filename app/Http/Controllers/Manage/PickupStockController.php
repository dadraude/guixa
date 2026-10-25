<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\AddStockToPickup;
use Guixa\Ordering\Application\RemoveStockFromPickup;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PickupStockController extends Controller
{
    public function store(Request $request, string $cycle, string $household, Actor $actor, AddStockToPickup $addStockToPickup): RedirectResponse
    {
        $data = $request->validate([
            'stock_product_id' => ['required', 'uuid'],
            'stock_quantity' => ['required', 'string', 'max:11'],
        ]);

        $addStockToPickup($actor, $cycle, $household, $data['stock_product_id'], $data['stock_quantity']);

        return back();
    }

    public function destroy(string $cycle, string $household, string $stockProduct, Actor $actor, RemoveStockFromPickup $removeStockFromPickup): RedirectResponse
    {
        $removeStockFromPickup($actor, $cycle, $household, $stockProduct);

        return back();
    }
}
