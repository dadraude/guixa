<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\AddBasketReturnRequest;
use Guixa\Sales\Application\AddReturnToBasket;
use Guixa\Sales\Application\RemoveReturnFromBasket;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

final class BasketReturnController extends Controller
{
    public function store(string $basket, AddBasketReturnRequest $request, Actor $actor, AddReturnToBasket $add): RedirectResponse
    {
        $add($actor, $basket, $request->string('return_item_id')->toString(), $request->string('return_quantity')->toString());

        return back();
    }

    public function destroy(string $basket, string $returnItem, Actor $actor, RemoveReturnFromBasket $remove): RedirectResponse
    {
        $remove($actor, $basket, $returnItem);

        return back();
    }
}
