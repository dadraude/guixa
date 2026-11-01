<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\ProductRepository;

final class GetProductProducer
{
    public function __construct(private readonly ProductRepository $products) {}

    public function __invoke(string $id): string
    {
        return ($this->products->get($id) ?? throw ProductNotFound::withId($id))->producerId;
    }
}
