<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\ProducerRow;
use Guixa\Catalog\Application\ListProducers;
use Guixa\Shared\Application\Actor;
use Inertia\Inertia;
use Inertia\Response;

/** The read-only producer list: administering producers stays with the Manager (R5). */
class ProducerController extends Controller
{
    public function index(Actor $actor, ListProducers $listProducers): Response
    {
        return Inertia::render('Catalogue/Producers/Index', [
            'producers' => array_map(
                fn ($producer) => ProducerRow::from($producer, null),
                $listProducers($actor),
            ),
        ]);
    }
}
