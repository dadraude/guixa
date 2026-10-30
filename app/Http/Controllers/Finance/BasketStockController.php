<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\AddBasketStockRequest;
use Guixa\Sales\Application\AddStockToBasket;
use Guixa\Sales\Application\RemoveStockFromBasket;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

final class BasketStockController extends Controller
{
    public function store(string $basket, AddBasketStockRequest $request, Actor $actor, AddStockToBasket $add): RedirectResponse
    {
        $add($actor, $basket, $request->string('stock_product_id')->toString(), $request->string('stock_quantity')->toString());

        return back();
    }

    public function destroy(string $basket, string $stockProduct, Actor $actor, RemoveStockFromBasket $remove): RedirectResponse
    {
        $remove($actor, $basket, $stockProduct);

        return back();
    }
}
