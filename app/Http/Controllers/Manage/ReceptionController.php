<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\HouseholdOptionRow;
use App\Http\ReceptionRow;
use Guixa\Catalog\Application\ListProducers;
use Guixa\Membership\Application\HouseholdSummary;
use Guixa\Membership\Application\ListHouseholds;
use Guixa\Ordering\Application\GetReception;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\ProducerNotInCycle;
use Guixa\Ordering\Domain\Error\ProducerOrderNotSent;
use Guixa\Shared\Application\Actor;
use Inertia\Inertia;
use Inertia\Response;

class ReceptionController extends Controller
{
    public function show(string $cycle, string $producer, Actor $actor, GetReception $getReception, ListProducers $listProducers, ListHouseholds $listHouseholds): Response
    {
        try {
            $reception = $getReception($actor, $cycle, $producer);
        } catch (CycleNotFound|ProducerNotInCycle|ProducerOrderNotSent) {
            abort(404);
        }

        $names = array_column(array_map(fn ($producer) => ['id' => $producer->id, 'name' => $producer->name], $listProducers($actor)), 'name', 'id');
        $active = array_filter($listHouseholds($actor), fn (HouseholdSummary $household) => $household->active);

        return Inertia::render('Manage/Reception/Show', [
            'reception' => ReceptionRow::from($reception, $names[$producer] ?? $producer),
            'distribution' => ['validated' => $reception->validated, 'distributed' => $reception->distributed, 'settled' => $reception->settled],
            'households' => array_map(HouseholdOptionRow::from(...), array_values($active)),
        ]);
    }
}
