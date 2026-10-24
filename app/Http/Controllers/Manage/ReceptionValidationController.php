<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Ordering\Application\ValidateReception;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

class ReceptionValidationController extends Controller
{
    public function store(string $cycle, string $producer, Actor $actor, ValidateReception $validateReception): RedirectResponse
    {
        $validateReception($actor, $cycle, $producer);

        return back();
    }
}
