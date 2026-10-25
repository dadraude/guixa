<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\SetReceivedTotal;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReceptionReceivedController extends Controller
{
    public function update(Request $request, string $cycle, string $producer, string $product, Actor $actor, SetReceivedTotal $setReceivedTotal): RedirectResponse
    {
        $data = $request->validate([
            'received' => ['required', 'string'],
            'overwrite_manual' => ['nullable', 'boolean'],
            'include_completed' => ['nullable', 'boolean'],
        ]);

        $setReceivedTotal($actor, $cycle, $producer, $product, $data['received'], (bool) ($data['overwrite_manual'] ?? false), isset($data['include_completed']) ? (bool) $data['include_completed'] : null);

        return back();
    }
}
