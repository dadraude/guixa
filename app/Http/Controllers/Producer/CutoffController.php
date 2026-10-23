<?php

namespace App\Http\Controllers\Producer;

use App\Http\Controllers\Controller;
use App\Http\CutoffRuleRow;
use Guixa\Catalog\Application\GetDefaultCutoff;
use Guixa\Catalog\Application\SetDefaultCutoff;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CutoffController extends Controller
{
    public function show(Actor $actor, GetDefaultCutoff $getDefaultCutoff): Response
    {
        return Inertia::render('Producer/Cutoff', [
            'cutoff' => CutoffRuleRow::from($getDefaultCutoff($actor, $this->producerId($actor))),
        ]);
    }

    public function update(Request $request, Actor $actor, SetDefaultCutoff $setDefaultCutoff): RedirectResponse
    {
        $data = $request->validate([
            'days_before' => ['required', 'integer'],
            'time' => ['required', 'string'],
        ]);

        $setDefaultCutoff($actor, $this->producerId($actor), (int) $data['days_before'], $data['time']);

        return back();
    }

    /** A producer role without a producer has nothing to configure. */
    private function producerId(Actor $actor): string
    {
        return $actor->producerId ?? abort(403);
    }
}
