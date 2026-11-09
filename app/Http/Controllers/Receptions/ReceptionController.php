<?php

namespace App\Http\Controllers\Receptions;

use App\Http\Controllers\Controller;
use App\Http\HouseholdOptionRow;
use App\Http\ReceptionRow;
use Guixa\Catalog\Application\GetProducer;
use Guixa\Ordering\Application\GetReception;
use Guixa\Ordering\Application\ListReceptionHouseholds;
use Guixa\Ordering\Application\ReceptionHouseholdData;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\ProducerNotInCycle;
use Guixa\Ordering\Domain\Error\ProducerOrderNotSent;
use Guixa\Shared\Application\Actor;
use Inertia\Inertia;
use Inertia\Response;

class ReceptionController extends Controller
{
    public function show(string $cycle, string $producer, Actor $actor, GetReception $getReception, GetProducer $getProducer, ListReceptionHouseholds $listHouseholds): Response
    {
        try {
            $reception = $getReception($actor, $cycle, $producer);
        } catch (CycleNotFound|ProducerNotInCycle|ProducerOrderNotSent) {
            abort(404);
        }

        return Inertia::render('Receptions/Show', [
            'reception' => ReceptionRow::from($reception, $getProducer($producer)?->name ?? $producer),
            'distribution' => ['validated' => $reception->validated, 'distributed' => $reception->distributed, 'settled' => $reception->settled, 'closed' => $reception->closed],
            'can' => [
                'edit' => $reception->canEdit,
                'validate' => $reception->canValidate,
                'distribute' => $reception->canDistribute,
                'reopen' => $reception->canReopen,
            ],
            'households' => array_map(fn (ReceptionHouseholdData $household) => new HouseholdOptionRow($household->id, $household->name), $listHouseholds($actor)),
        ]);
    }
}
