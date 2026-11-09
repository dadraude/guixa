<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\ProducerProductRow;
use Guixa\Catalog\Application\GetProducer;
use Guixa\Catalog\Application\ListProductsOfProducer;
use Guixa\Catalog\Application\ProductAdministrationData;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProducerProductController extends Controller
{
    public function index(string $producer, Actor $actor, GetProducer $getProducer, ListProductsOfProducer $list): Response
    {
        $record = $getProducer($producer);
        abort_if($record === null, 404);

        return Inertia::render('Catalogue/Producers/Products', [
            'producer' => ['id' => $record->id, 'name' => $record->name],
            'products' => array_map($this->row(...), $list($actor, $producer)),
            'canCreateStock' => Gate::forUser($actor)->allows('manage'),
        ]);
    }

    /** @return array<string, mixed> */
    private function row(ProductAdministrationData $product): array
    {
        return [
            ...get_object_vars(ProducerProductRow::from($product)),
            'can' => [
                'edit' => $product->canEdit,
                'changeClassification' => $product->canChangeClassification,
                'delete' => $product->canDelete,
            ],
        ];
    }
}
