<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\AddReturnToPickup;
use Guixa\Ordering\Application\RemoveReturnFromPickup;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PickupReturnController extends Controller
{
    public function store(Request $request, string $cycle, string $household, Actor $actor, AddReturnToPickup $addReturnToPickup): RedirectResponse
    {
        $data = $request->validate([
            'return_item_id' => ['required', 'uuid'],
            'return_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $addReturnToPickup($actor, $cycle, $household, $data['return_item_id'], (int) $data['return_quantity']);

        return back();
    }

    public function destroy(string $cycle, string $household, string $returnItem, Actor $actor, RemoveReturnFromPickup $removeReturnFromPickup): RedirectResponse
    {
        $removeReturnFromPickup($actor, $cycle, $household, $returnItem);

        return back();
    }
}
