<?php

namespace App\Http\Controllers\Producer;

use App\Http\Controllers\Controller;
use App\Http\ProducerStockRow;
use App\Http\ProductRow;
use App\Http\Requests\Producer\ProductRequest;
use Guixa\Catalog\Application\CreateProduct;
use Guixa\Catalog\Application\DeactivateProduct;
use Guixa\Catalog\Application\DeleteUnusedProduct;
use Guixa\Catalog\Application\GetProductProducer;
use Guixa\Catalog\Application\IsProductUsed;
use Guixa\Catalog\Application\ListProducerProducts;
use Guixa\Catalog\Application\ListProducerStockProducts;
use Guixa\Catalog\Application\ReactivateProduct;
use Guixa\Catalog\Application\UpdateProduct;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Actor $actor, ListProducerProducts $list, ListProducerStockProducts $stock): Response
    {
        $producerId = $this->producerId($actor);

        return Inertia::render('Producer/Products/Index', [
            'products' => array_map(ProductRow::from(...), $list($actor, $producerId)),
            'stockProducts' => array_map(ProducerStockRow::from(...), $stock($actor, $producerId)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Producer/Products/Create');
    }

    public function store(ProductRequest $request, Actor $actor, CreateProduct $create): RedirectResponse
    {
        $create(
            $actor,
            $this->producerId($actor),
            $request->string('name')->toString(),
            $request->string('unit')->toString(),
            $request->priceCents(),
            $request->boolean('variable'),
            $request->containersPerUnit(),
        );

        return to_route('producer.products.index');
    }

    public function edit(string $product, Actor $actor, ListProducerProducts $list, IsProductUsed $used): Response
    {
        $row = collect($list($actor, $this->producerId($actor)))->firstWhere('id', $product);
        abort_if($row === null, 404);

        return Inertia::render('Producer/Products/Edit', ['product' => ProductRow::from($row), 'used' => $used($product)]);
    }

    public function update(string $product, ProductRequest $request, Actor $actor, UpdateProduct $update, GetProductProducer $producerOf): RedirectResponse
    {
        $this->ownProduct($product, $actor, $producerOf);
        $update(
            $actor,
            $product,
            $request->string('name')->toString(),
            $request->string('unit')->toString(),
            $request->priceCents(),
            $request->boolean('variable'),
            $request->containersPerUnit(),
        );

        return to_route('producer.products.index');
    }

    public function destroy(string $product, Actor $actor, DeactivateProduct $deactivate, GetProductProducer $producerOf): RedirectResponse
    {
        $this->ownProduct($product, $actor, $producerOf);
        $deactivate($actor, $product);

        return to_route('producer.products.index');
    }

    public function deleteUnused(string $product, Actor $actor, DeleteUnusedProduct $delete, GetProductProducer $producerOf): RedirectResponse
    {
        $this->ownProduct($product, $actor, $producerOf);
        $delete($actor, $product);

        return to_route('producer.products.index');
    }

    public function reactivate(string $product, Actor $actor, ReactivateProduct $reactivate, GetProductProducer $producerOf): RedirectResponse
    {
        $this->ownProduct($product, $actor, $producerOf);
        $reactivate($actor, $product);

        return to_route('producer.products.index');
    }

    /** The Producer area only reaches the actor's own products; other producers' are for the Catàleg area (R3, S5). */
    private function ownProduct(string $product, Actor $actor, GetProductProducer $producerOf): void
    {
        abort_unless($producerOf($product) === $this->producerId($actor), 403);
    }

    /** A producer role without a producer has nothing to manage. */
    private function producerId(Actor $actor): string
    {
        return $actor->producerId ?? abort(403);
    }
}
