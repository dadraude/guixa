<?php

namespace App\Http\Controllers\Producer;

use App\Http\Controllers\Controller;
use App\Http\ProducerCycleSummaryRow;
use Guixa\Ordering\Application\GetProducerCycleSummary;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Inertia\Inertia;
use Inertia\Response;

class CycleController extends Controller
{
    public function show(string $cycle, Actor $actor, GetProducerCycleSummary $getProducerCycleSummary): Response
    {
        try {
            $summary = $getProducerCycleSummary($actor, $cycle);
        } catch (CycleNotFound) {
            abort(404);
        }

        return Inertia::render('Producer/CycleSummary', [
            'summary' => ProducerCycleSummaryRow::from($summary),
        ]);
    }
}
