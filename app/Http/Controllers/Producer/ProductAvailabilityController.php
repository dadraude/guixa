<?php

namespace App\Http\Controllers\Producer;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\SetProductAvailability;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductAvailabilityController extends Controller
{
    /** Marks one of the producer's products unavailable in a cycle, or available again (R3c). */
    public function update(Request $request, string $cycle, string $product, Actor $actor, SetProductAvailability $setProductAvailability): RedirectResponse
    {
        $data = $request->validate(['available' => ['required', 'boolean']]);

        $setProductAvailability($actor, $cycle, $product, (bool) $data['available']);

        return back();
    }
}
