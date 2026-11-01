<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

final class BasketCollectionController extends Controller
{
    public function update(string $basket, Actor $actor, MarkBasketCollected $collect): RedirectResponse
    {
        $collect($actor, $basket);

        return back();
    }
}
