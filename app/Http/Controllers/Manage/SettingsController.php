<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\GetPickupWeekday;
use Guixa\Ordering\Application\SetPickupWeekday;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function show(Actor $actor, GetPickupWeekday $getPickupWeekday): Response
    {
        return Inertia::render('Manage/Settings', ['pickupWeekday' => $getPickupWeekday($actor)]);
    }

    public function updatePickupWeekday(Request $request, Actor $actor, SetPickupWeekday $setPickupWeekday): RedirectResponse
    {
        $data = $request->validate(['pickup_weekday' => ['required', 'integer', 'between:1,7']]);

        $setPickupWeekday($actor, (int) $data['pickup_weekday']);

        return back();
    }
}
