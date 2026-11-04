<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\PickupSummaryRow;
use Guixa\Ordering\Application\GetCycle;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Sales\Application\FindWeeklyBaseBasket;
use Guixa\Sales\Application\ListWeeklyBaskets;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Where staff find the weekly base baskets of a settled cycle; Sales owns validating and collecting them (R19, R24). */
class PickupController extends Controller
{
    public function index(string $cycle, Actor $actor, ListWeeklyBaskets $listWeeklyBaskets, GetCycle $getCycle): Response
    {
        try {
            $data = $getCycle($actor, $cycle);
        } catch (CycleNotFound) {
            abort(404);
        }
        abort_unless($data->settled, 404);

        return Inertia::render('Manage/Pickups/Index', [
            'cycleId' => $cycle,
            'closed' => $data->closed,
            'pickups' => array_map(PickupSummaryRow::from(...), $listWeeklyBaskets($actor, $cycle)),
        ]);
    }

    /** A household's weekly base basket is a Sales basket: the staff page is the basket page. */
    public function show(string $cycle, string $household, Actor $actor, FindWeeklyBaseBasket $findBase): RedirectResponse
    {
        $basket = $findBase($actor, $cycle, $household) ?? abort(404);

        return to_route('finance.baskets.show', ['basket' => $basket->id]);
    }
}
