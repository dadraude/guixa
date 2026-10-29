<?php

namespace App\Http\Controllers\Producer;

use App\Http\Controllers\Controller;
use App\Http\ProductRow;
use App\Http\Requests\Producer\ProductRequest;
use Guixa\Catalog\Application\CreateProduct;
use Guixa\Catalog\Application\DeactivateProduct;
use Guixa\Catalog\Application\DeleteUnusedProduct;
use Guixa\Catalog\Application\IsProductUsed;
use Guixa\Catalog\Application\ListProducerProducts;
use Guixa\Catalog\Application\ReactivateProduct;
use Guixa\Catalog\Application\UpdateProduct;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Actor $actor, ListProducerProducts $list): Response
    {
        return Inertia::render('Producer/Products/Index', [
            'products' => array_map(ProductRow::from(...), $list($actor, $this->producerId($actor))),
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

    public function update(string $product, ProductRequest $request, Actor $actor, UpdateProduct $update): RedirectResponse
    {
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

    public function destroy(string $product, Actor $actor, DeactivateProduct $deactivate): RedirectResponse
    {
        $deactivate($actor, $product);

        return to_route('producer.products.index');
    }

    public function deleteUnused(string $product, Actor $actor, DeleteUnusedProduct $delete): RedirectResponse
    {
        $delete($actor, $product);

        return to_route('producer.products.index');
    }

    public function reactivate(string $product, Actor $actor, ReactivateProduct $reactivate): RedirectResponse
    {
        $reactivate($actor, $product);

        return to_route('producer.products.index');
    }

    /** A producer role without a producer has nothing to manage. */
    private function producerId(Actor $actor): string
    {
        return $actor->producerId ?? abort(403);
    }
}
