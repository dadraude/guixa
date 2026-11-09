<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use Guixa\Catalog\Application\GetProducer;
use Guixa\Catalog\Application\GetProductProducer;
use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Ordering\Application\GetProducerCycleAvailability;
use Guixa\Ordering\Application\ListProducerUnsentCycles;
use Guixa\Ordering\Application\ProducerCycleAvailabilityData;
use Guixa\Ordering\Application\ProducerUnsentCycleData;
use Guixa\Ordering\Application\SetProductAvailability;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** A producer's products per unsent cycle, for the accounts that administer the catalogue (R3, R5, S13). */
class ProducerAvailabilityController extends Controller
{
    public function show(string $producer, Actor $actor, GetProducer $getProducer, ListProducerUnsentCycles $listCycles, GetProducerCycleAvailability $getAvailability): Response
    {
        $record = $getProducer($producer);
        abort_if($record === null, 404);

        return Inertia::render('Catalogue/Producers/Availability', [
            'producer' => ['id' => $record->id, 'name' => $record->name, 'active' => $record->active],
            'cycles' => array_map(
                fn (ProducerUnsentCycleData $cycle) => $this->row($getAvailability($actor, $cycle->cycleId, $producer)),
                $listCycles($actor, $producer),
            ),
        ]);
    }

    /** Marks one of the producer's products unavailable in a cycle, or available again (R3c). */
    public function update(string $producer, string $cycle, string $product, Request $request, Actor $actor, SetProductAvailability $setAvailability, GetProductProducer $producerOf, GetProducerCycleAvailability $getAvailability): RedirectResponse
    {
        $this->ensureBelongs($producer, $product, $producerOf);
        $data = $request->validate(['available' => ['required', 'boolean']]);

        try {
            // Only a cycle the producer takes part in and that is not cancelled can be administered (D9).
            $getAvailability($actor, $cycle, $producer);
            $setAvailability($actor, $cycle, $product, (bool) $data['available']);
        } catch (CycleNotFound) {
            abort(404);
        }

        return back();
    }

    /** @return array<string, mixed> */
    private function row(ProducerCycleAvailabilityData $availability): array
    {
        return [
            'cycleId' => $availability->cycleId,
            'pickupDate' => $availability->pickupDate,
            'canChange' => $availability->canChange,
            'products' => array_map(get_object_vars(...), $availability->products),
        ];
    }

    /** A product is only reachable under its own producer, so a stale or forged pair never acts on another producer's product. */
    private function ensureBelongs(string $producer, string $product, GetProductProducer $producerOf): void
    {
        try {
            abort_unless($producerOf($product) === $producer, 404);
        } catch (ProductNotFound) {
            abort(404);
        }
    }
}
