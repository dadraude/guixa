<?php

namespace App\Http\Controllers\Receptions;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\ListReceptionCycles;
use Guixa\Ordering\Application\ReceptionCycleData;
use Guixa\Ordering\Application\ReceptionCycleProducerData;
use Guixa\Shared\Application\Actor;
use Inertia\Inertia;
use Inertia\Response;

class ReceptionCycleController extends Controller
{
    public function index(Actor $actor, ListReceptionCycles $listReceptionCycles): Response
    {
        return Inertia::render('Receptions/Index', [
            'cycles' => array_map(fn (ReceptionCycleData $cycle) => [
                'cycleId' => $cycle->cycleId,
                'pickupDate' => $cycle->pickupDate,
                'closed' => $cycle->closed,
                'producers' => array_map(fn (ReceptionCycleProducerData $producer) => [
                    'producerId' => $producer->producerId,
                    'producerName' => $producer->producerName,
                    'state' => $producer->state->value,
                ], $cycle->producers),
            ], $listReceptionCycles($actor)),
        ]);
    }
}
