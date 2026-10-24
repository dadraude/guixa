<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\AddUnorderedProduct;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReceptionProductController extends Controller
{
    public function store(Request $request, string $cycle, string $producer, Actor $actor, AddUnorderedProduct $addUnorderedProduct): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'uuid'],
            'received' => ['nullable', 'string'],
        ]);

        $addUnorderedProduct($actor, $cycle, $producer, $data['product_id'], $data['received'] ?? '0');

        return back();
    }
}
