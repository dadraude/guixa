<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Guixa\Sales\Application\ValidateBasket;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

final class BasketValidationController extends Controller
{
    public function store(string $basket, Actor $actor, ValidateBasket $validate): RedirectResponse
    {
        $validate($actor, $basket);

        return back();
    }
}
