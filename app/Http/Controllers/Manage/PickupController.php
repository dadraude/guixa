<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\HouseholdOptionRow;
use App\Http\PickupRow;
use App\Http\PickupSummaryRow;
use App\Http\ReturnItemRow;
use App\Http\StockLevelRow;
use Guixa\Membership\Application\HouseholdSummary;
use Guixa\Membership\Application\ListHouseholds;
use Guixa\Ordering\Application\GetPickup;
use Guixa\Ordering\Application\ListPickups;
use Guixa\Ordering\Application\OpenPickup;
use Guixa\Ordering\Application\PickupSummaryData;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\CycleNotSettled;
use Guixa\Ordering\Domain\Error\PickupNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\ListReturnItems;
use Guixa\Stock\Application\ListStock;
use Guixa\Stock\Application\StockLevelData;
use Guixa\Wallet\Application\GetBalance;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PickupController extends Controller
{
    /** The pickups of a settled cycle, and the active households that have none, for a stock-only pickup (R19). */
    public function index(string $cycle, Actor $actor, ListPickups $listPickups, ListHouseholds $listHouseholds): Response
    {
        try {
            $pickups = $listPickups($actor, $cycle);
        } catch (CycleNotFound|CycleNotSettled) {
            abort(404);
        }

        $withPickup = array_flip(array_map(fn (PickupSummaryData $pickup) => $pickup->householdId, $pickups));
        $without = array_filter($listHouseholds($actor), fn (HouseholdSummary $household) => $household->active && ! isset($withPickup[$household->id]));

        return Inertia::render('Manage/Pickups/Index', [
            'cycleId' => $cycle,
            'pickups' => array_map(PickupSummaryRow::from(...), $pickups),
            'households' => array_map(HouseholdOptionRow::from(...), array_values($without)),
        ]);
    }

    public function show(string $cycle, string $household, Actor $actor, GetPickup $getPickup, GetBalance $getBalance, ListStock $listStock, ListReturnItems $listReturnItems): Response
    {
        try {
            $pickup = $getPickup($actor, $cycle, $household);
        } catch (CycleNotFound|CycleNotSettled|PickupNotFound) {
            abort(404);
        }

        return Inertia::render('Manage/Pickups/Show', [
            'pickup' => PickupRow::from($pickup, $getBalance($actor, $household)),
            // A deactivated stock product cannot be added (R20); a deactivated return item can (R20a).
            'stockProducts' => array_map(StockLevelRow::from(...), array_values(array_filter($listStock($actor), fn (StockLevelData $level) => $level->active))),
            'returnItems' => array_map(ReturnItemRow::from(...), $listReturnItems($actor)),
        ]);
    }

    public function store(string $cycle, string $household, Actor $actor, OpenPickup $openPickup): RedirectResponse
    {
        $openPickup($actor, $cycle, $household);

        return to_route('manage.cycles.pickups.show', ['cycle' => $cycle, 'household' => $household]);
    }
}
