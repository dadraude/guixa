<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\PickupSummaryRow;
use Guixa\Ordering\Application\GetCycle;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Sales\Application\BasketData;
use Guixa\Sales\Application\ListBaskets;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Where staff find the weekly base baskets of a settled cycle; Sales owns validating and collecting them (R19, R24). */
class PickupController extends Controller
{
    public function index(string $cycle, Actor $actor, ListBaskets $listBaskets, GetCycle $getCycle): Response
    {
        try {
            $data = $getCycle($actor, $cycle);
        } catch (CycleNotFound) {
            abort(404);
        }
        abort_unless($data->settled, 404);

        $baskets = array_filter($listBaskets($actor), fn (BasketData $basket) => $basket->kind === 'weekly' && $basket->referenceCycleId === $cycle);
        usort($baskets, fn (BasketData $a, BasketData $b) => [mb_strtolower($a->householdName), $a->householdId, $a->late, $a->id] <=> [mb_strtolower($b->householdName), $b->householdId, $b->late, $b->id]);

        return Inertia::render('Manage/Pickups/Index', [
            'cycleId' => $cycle,
            'closed' => $data->closed,
            'pickups' => array_map(PickupSummaryRow::from(...), $baskets),
        ]);
    }

    /** A household's weekly base basket is a Sales basket: the staff page is the basket page. */
    public function show(string $cycle, string $household, Actor $actor, ListBaskets $listBaskets): RedirectResponse
    {
        foreach ($listBaskets($actor) as $basket) {
            if ($basket->kind === 'weekly' && ! $basket->late && $basket->referenceCycleId === $cycle && $basket->householdId === $household) {
                return to_route('finance.baskets.show', ['basket' => $basket->id]);
            }
        }

        abort(404);
    }
}
