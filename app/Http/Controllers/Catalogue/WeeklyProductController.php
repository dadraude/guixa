<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\EuroAmount;
use App\Http\ProductRow;
use App\Http\Requests\Producer\ProductRequest;
use Guixa\Catalog\Application\CreateProduct;
use Guixa\Catalog\Application\DeactivateProduct;
use Guixa\Catalog\Application\DeleteUnusedProduct;
use Guixa\Catalog\Application\GetProducer;
use Guixa\Catalog\Application\GetProductProducer;
use Guixa\Catalog\Application\ListProductsOfProducer;
use Guixa\Catalog\Application\ReactivateProduct;
use Guixa\Catalog\Application\UpdateProduct;
use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Any producer's weekly products, under the rules a producer has for its own (R3). */
class WeeklyProductController extends Controller
{
    public function create(string $producer, GetProducer $getProducer): Response
    {
        return Inertia::render('Catalogue/Producers/WeeklyProduct/Create', ['producer' => $this->producer($producer, $getProducer)]);
    }

    public function store(string $producer, ProductRequest $request, Actor $actor, CreateProduct $create, GetProducer $getProducer): RedirectResponse
    {
        $this->producer($producer, $getProducer);
        $create(
            $actor,
            $producer,
            $request->string('name')->toString(),
            $request->string('unit')->toString(),
            $request->priceCents(),
            $request->boolean('variable'),
            $request->containersPerUnit(),
        );

        return $this->backToProducts($producer);
    }

    public function edit(string $producer, string $product, Actor $actor, GetProducer $getProducer, ListProductsOfProducer $list): Response
    {
        $row = collect($list($actor, $producer))->first(fn ($candidate) => $candidate->id === $product && ! $candidate->stock);
        abort_if($row === null, 404);
        abort_unless($row->canEdit, 403);

        return Inertia::render('Catalogue/Producers/WeeklyProduct/Edit', [
            'producer' => $this->producer($producer, $getProducer),
            'product' => new ProductRow(
                $row->id, $row->name, $row->unit, EuroAmount::format($row->priceCents),
                $row->variable, $row->containersPerUnit, $row->active,
            ),
            'used' => $row->used,
            'canDelete' => $row->canDelete,
        ]);
    }

    public function update(string $producer, string $product, ProductRequest $request, Actor $actor, UpdateProduct $update, GetProductProducer $producerOf): RedirectResponse
    {
        $this->ensureBelongs($producer, $product, $producerOf);
        $update(
            $actor,
            $product,
            $request->string('name')->toString(),
            $request->string('unit')->toString(),
            $request->priceCents(),
            $request->boolean('variable'),
            $request->containersPerUnit(),
        );

        return $this->backToProducts($producer);
    }

    public function destroy(string $producer, string $product, Actor $actor, DeactivateProduct $deactivate, GetProductProducer $producerOf): RedirectResponse
    {
        $this->ensureBelongs($producer, $product, $producerOf);
        $deactivate($actor, $product);

        return $this->backToProducts($producer);
    }

    public function reactivate(string $producer, string $product, Actor $actor, ReactivateProduct $reactivate, GetProductProducer $producerOf): RedirectResponse
    {
        $this->ensureBelongs($producer, $product, $producerOf);
        $reactivate($actor, $product);

        return $this->backToProducts($producer);
    }

    public function deleteUnused(string $producer, string $product, Actor $actor, DeleteUnusedProduct $delete, GetProductProducer $producerOf): RedirectResponse
    {
        $this->ensureBelongs($producer, $product, $producerOf);
        $delete($actor, $product);

        return $this->backToProducts($producer);
    }

    /** A product is only reachable under its own producer, so a stale or forged pair never acts on another producer's product. */
    private function ensureBelongs(string $producer, string $product, GetProductProducer $producerOf): void
    {
        try {
            abort_unless($producerOf($product) === $producer, 404);
        } catch (ProductNotFound) {
            abort(404);
        }
    }

    /** @return array{id: string, name: string} */
    private function producer(string $producer, GetProducer $getProducer): array
    {
        $record = $getProducer($producer);
        abort_if($record === null, 404);

        return ['id' => $record->id, 'name' => $record->name];
    }

    private function backToProducts(string $producer): RedirectResponse
    {
        return to_route('catalogue.producers.products.index', ['producer' => $producer]);
    }
}
