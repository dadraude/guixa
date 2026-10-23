<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\ClearCutoff;
use Guixa\Ordering\Application\CloseProducerNow;
use Guixa\Ordering\Application\SetCutoff;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CycleCutoffController extends Controller
{
    public function update(Request $request, string $cycle, string $producer, Actor $actor, SetCutoff $setCutoff): RedirectResponse
    {
        $data = $request->validate(['cutoff' => ['required', 'string']]);

        $setCutoff($actor, $cycle, $producer, $data['cutoff']);

        return back();
    }

    public function close(string $cycle, string $producer, Actor $actor, CloseProducerNow $closeProducerNow): RedirectResponse
    {
        $closeProducerNow($actor, $cycle, $producer);

        return back();
    }

    public function destroy(string $cycle, string $producer, Actor $actor, ClearCutoff $clearCutoff): RedirectResponse
    {
        $clearCutoff($actor, $cycle, $producer);

        return back();
    }
}
