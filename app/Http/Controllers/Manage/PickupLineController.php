<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\AdjustDeliveredQuantity;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PickupLineController extends Controller
{
    public function update(Request $request, string $cycle, string $household, string $product, Actor $actor, AdjustDeliveredQuantity $adjustDeliveredQuantity): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'string']]);

        $adjustDeliveredQuantity($actor, $cycle, $household, $product, $data['quantity']);

        return back();
    }
}
