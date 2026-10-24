<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\AddParticipant;
use Guixa\Ordering\Application\ParticipateAgain;
use Guixa\Ordering\Application\StopParticipating;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

class CycleParticipantController extends Controller
{
    public function store(string $cycle, string $producer, Actor $actor, AddParticipant $addParticipant): RedirectResponse
    {
        $addParticipant($actor, $cycle, $producer);

        return back();
    }

    public function update(string $cycle, string $producer, Actor $actor, ParticipateAgain $participateAgain): RedirectResponse
    {
        $participateAgain($actor, $cycle, $producer);

        return back();
    }

    public function destroy(string $cycle, string $producer, Actor $actor, StopParticipating $stopParticipating): RedirectResponse
    {
        $stopParticipating($actor, $cycle, $producer);

        return back();
    }
}
