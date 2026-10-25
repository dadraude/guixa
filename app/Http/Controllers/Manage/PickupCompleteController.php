<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\EuroAmount;
use Guixa\Ordering\Application\CompletePickup;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PickupCompleteController extends Controller
{
    public function store(Request $request, string $cycle, string $household, Actor $actor, CompletePickup $completePickup): RedirectResponse
    {
        $data = $request->validate([
            'top_up' => ['nullable', 'string', 'regex:'.EuroAmount::PATTERN],
        ]);

        $topUp = $data['top_up'] ?? null;

        $completePickup($actor, $cycle, $household, $topUp === null ? null : EuroAmount::toCents($topUp));

        return to_route('manage.cycles.pickups.index', ['cycle' => $cycle]);
    }
}
