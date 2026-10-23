<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\HouseholdRow;
use App\Http\HouseholdSummaryRow;
use Guixa\Membership\Application\CreateHousehold;
use Guixa\Membership\Application\GetHousehold;
use Guixa\Membership\Application\HouseholdSummary;
use Guixa\Membership\Application\ListHouseholds;
use Guixa\Membership\Application\RenameHousehold;
use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdController extends Controller
{
    public function index(Actor $actor, ListHouseholds $listHouseholds): Response
    {
        return Inertia::render('Manage/Households/Index', [
            'households' => array_map(HouseholdSummaryRow::from(...), $listHouseholds($actor)),
        ]);
    }

    public function store(Request $request, Actor $actor, CreateHousehold $createHousehold): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $id = $createHousehold($actor, $data['name']);

        return to_route('manage.households.show', $id);
    }

    public function show(string $household, Actor $actor, GetHousehold $getHousehold, ListHouseholds $listHouseholds): Response
    {
        try {
            $detail = $getHousehold($actor, $household);
        } catch (HouseholdNotFound) {
            abort(404);
        }

        return Inertia::render('Manage/Households/Show', [
            'household' => HouseholdRow::from($detail),
            'otherHouseholds' => array_values(array_map(
                HouseholdSummaryRow::from(...),
                array_filter($listHouseholds($actor), fn (HouseholdSummary $other) => $other->id !== $detail->id),
            )),
        ]);
    }

    public function update(string $household, Request $request, Actor $actor, RenameHousehold $renameHousehold): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $renameHousehold($actor, $household, $data['name']);

        return back();
    }
}
