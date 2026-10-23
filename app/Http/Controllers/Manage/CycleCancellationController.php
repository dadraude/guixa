<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\CancelCycle;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

class CycleCancellationController extends Controller
{
    public function store(string $cycle, Actor $actor, CancelCycle $cancelCycle): RedirectResponse
    {
        $cancelCycle($actor, $cycle);

        return back();
    }
}
