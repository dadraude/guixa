<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Sales\Application\FindWeeklyBaseBasket;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

/** Where staff find the weekly base baskets of a settled cycle; Sales owns validating and collecting them (R19, R24). */
class PickupController extends Controller
{
    /** A household's weekly base basket is a Sales basket: the staff page is the basket page. */
    public function show(string $cycle, string $household, Actor $actor, FindWeeklyBaseBasket $findBase): RedirectResponse
    {
        $basket = $findBase($actor, $cycle, $household) ?? abort(404);

        return to_route('finance.baskets.show', ['basket' => $basket->id]);
    }
}
