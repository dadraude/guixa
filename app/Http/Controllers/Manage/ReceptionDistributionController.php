<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\MarkProducerDistributed;
use Guixa\Ordering\Application\ReopenProducerOrder;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

class ReceptionDistributionController extends Controller
{
    public function store(string $cycle, string $producer, Actor $actor, MarkProducerDistributed $markProducerDistributed): RedirectResponse
    {
        $markProducerDistributed($actor, $cycle, $producer);

        return back();
    }

    public function destroy(string $cycle, string $producer, Actor $actor, ReopenProducerOrder $reopenProducerOrder): RedirectResponse
    {
        $reopenProducerOrder($actor, $cycle, $producer);

        return back();
    }
}
