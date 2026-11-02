<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreBasketCorrectionRequest;
use Guixa\Sales\Application\CorrectBasket;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

final class BasketCorrectionController extends Controller
{
    public function store(string $basket, StoreBasketCorrectionRequest $request, Actor $actor, CorrectBasket $correct): RedirectResponse
    {
        $correct($actor, $basket, $request->integer('amount_cents'), $request->string('reason')->toString(),
            $request->string('correction_id')->toString());

        return back();
    }
}
