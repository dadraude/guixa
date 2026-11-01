<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\ProducerProductRow;
use Guixa\Catalog\Application\GetProducer;
use Guixa\Catalog\Application\ListProductsOfProducer;
use Guixa\Shared\Application\Actor;
use Inertia\Inertia;
use Inertia\Response;

class ProducerProductController extends Controller
{
    public function index(string $producer, Actor $actor, GetProducer $getProducer, ListProductsOfProducer $list): Response
    {
        $record = $getProducer($producer);
        abort_if($record === null, 404);

        return Inertia::render('Manage/Producers/Products', [
            'producer' => ['id' => $record->id, 'name' => $record->name],
            'products' => array_map(ProducerProductRow::from(...), $list($actor, $producer)),
        ]);
    }
}
