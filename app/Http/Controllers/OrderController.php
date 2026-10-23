<?php

namespace App\Http\Controllers;

use App\Http\OrderRow;
use Guixa\Catalog\Application\GetProducer;
use Guixa\Ordering\Application\GetHouseholdOrder;
use Guixa\Ordering\Application\RemoveMarkedLine;
use Guixa\Ordering\Application\SetOrderLine;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /** The member's household order in a cycle (R7). */
    public function edit(string $cycle, Actor $actor, GetHouseholdOrder $getHouseholdOrder, GetProducer $getProducer): Response
    {
        try {
            $order = $getHouseholdOrder($actor, $cycle);
        } catch (CycleNotFound) {
            abort(404);
        }

        $producerIds = array_unique([
            ...array_map(fn ($producer) => $producer->producerId, $order->producers),
            ...array_map(fn ($line) => $line->producerId, $order->lines),
        ]);
        $names = [];

        foreach ($producerIds as $producerId) {
            $names[$producerId] = $getProducer($producerId)->name ?? $producerId;
        }

        return Inertia::render('Orders/Edit', ['order' => OrderRow::from($order, $names)]);
    }

    /** Sets a line's quantity; zero removes it (R9). */
    public function updateLine(Request $request, string $cycle, string $product, Actor $actor, SetOrderLine $setOrderLine): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'string']]);

        $setOrderLine($actor, $cycle, $product, $data['quantity']);

        return back();
    }

    /** Removes a line marked *will not be delivered*, even after its producer's cutoff (R3b, R8). */
    public function removeMarkedLine(string $cycle, string $product, Actor $actor, RemoveMarkedLine $removeMarkedLine): RedirectResponse
    {
        $removeMarkedLine($actor, $cycle, $product);

        return back();
    }
}
