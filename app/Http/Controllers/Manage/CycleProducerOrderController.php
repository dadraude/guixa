<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\SendProducerOrderManually;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

class CycleProducerOrderController extends Controller
{
    public function send(string $cycle, string $producer, Actor $actor, SendProducerOrderManually $sendProducerOrder): RedirectResponse
    {
        $sendProducerOrder($actor, $cycle, $producer);

        return back()->with('status', __('Order sent.'));
    }
}
