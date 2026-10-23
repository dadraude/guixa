<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\AddParticipant;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

class CycleParticipantController extends Controller
{
    public function store(string $cycle, string $producer, Actor $actor, AddParticipant $addParticipant): RedirectResponse
    {
        $addParticipant($actor, $cycle, $producer);

        return back();
    }
}
