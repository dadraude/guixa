<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreBasketCollectionCorrectionRequest;
use Guixa\Sales\Application\CorrectBasketCollection;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

final class BasketCollectionCorrectionController extends Controller
{
    public function store(string $basket, StoreBasketCollectionCorrectionRequest $request, Actor $actor,
        CorrectBasketCollection $correct): RedirectResponse
    {
        $correct($actor, $basket, $request->string('reason')->toString(), $request->string('correction_id')->toString());

        return back();
    }
}
