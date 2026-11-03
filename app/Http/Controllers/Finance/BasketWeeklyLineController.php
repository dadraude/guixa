<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SetWeeklyQuantityRequest;
use Guixa\Sales\Application\SetBasketActualQuantity;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

final class BasketWeeklyLineController extends Controller
{
    public function update(string $basket, string $product, SetWeeklyQuantityRequest $request, Actor $actor, SetBasketActualQuantity $set): RedirectResponse
    {
        $set($actor, $basket, $product, $request->string('quantity')->toString());

        return back();
    }
}
