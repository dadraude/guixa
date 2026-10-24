<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\SetAllocation;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReceptionAllocationController extends Controller
{
    public function update(Request $request, string $cycle, string $producer, string $product, string $household, Actor $actor, SetAllocation $setAllocation): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'string']]);

        $setAllocation($actor, $cycle, $producer, $product, $household, $data['quantity']);

        return back();
    }
}
