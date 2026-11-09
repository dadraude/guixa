<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\ProductClassificationRequest;
use Guixa\Catalog\Application\ChangeProductClassification;
use Guixa\Catalog\Application\GetProductProducer;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;

class ProductClassificationController extends Controller
{
    public function update(string $product, ProductClassificationRequest $request, Actor $actor, ChangeProductClassification $change, GetProductProducer $producer): RedirectResponse
    {
        $producerId = $producer($product);
        $change($actor, $product, $request->boolean('stock'));

        return to_route('catalogue.producers.products.index', ['producer' => $producerId]);
    }
}
