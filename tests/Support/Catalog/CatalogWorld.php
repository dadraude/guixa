<?php

namespace Tests\Support\Catalog;

use Guixa\Catalog\Application\CreateProducer;
use Guixa\Catalog\Application\CreateProduct;
use Guixa\Catalog\Application\DeactivateProducer;
use Guixa\Catalog\Application\DeactivateProduct;
use Guixa\Catalog\Application\DeleteUnusedProduct;
use Guixa\Catalog\Application\GetDefaultCutoff;
use Guixa\Catalog\Application\GetOrderableProduct;
use Guixa\Catalog\Application\GetProducer;
use Guixa\Catalog\Application\GetProducerCutoffRule;
use Guixa\Catalog\Application\ListOrderableProducts;
use Guixa\Catalog\Application\ListProducerProducts;
use Guixa\Catalog\Application\ListProducerProductsForReception;
use Guixa\Catalog\Application\ListProducers;
use Guixa\Catalog\Application\ListProductsOfProducer;
use Guixa\Catalog\Application\ReactivateProducer;
use Guixa\Catalog\Application\ReactivateProduct;
use Guixa\Catalog\Application\SetDefaultCutoff;
use Guixa\Catalog\Application\UpdateProduct;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\FakeProducerAccess;
use Tests\Support\InMemory\FakeProducerCycles;
use Tests\Support\InMemory\FakeProductUsage;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\InMemoryProducerRepository;
use Tests\Support\InMemory\InMemoryProductRepository;

/** The Catalog use cases wired to in-memory adapters, for the Application tests. */
final class CatalogWorld
{
    public readonly FrozenClock $clock;

    public readonly InMemoryProducerRepository $producers;

    public readonly InMemoryProductRepository $products;

    public readonly FakeProducerCycles $cycles;

    public readonly FakeProductUsage $usage;

    public readonly ImmediateTransactionRunner $transactions;

    public readonly FakeProducerAccess $access;

    public readonly CreateProducer $createProducer;

    public readonly DeactivateProducer $deactivateProducer;

    public readonly ReactivateProducer $reactivateProducer;

    public readonly ListProducers $listProducers;

    public readonly GetProducer $getProducer;

    public readonly SetDefaultCutoff $setDefaultCutoff;

    public readonly GetDefaultCutoff $getDefaultCutoff;

    public readonly CreateProduct $createProduct;

    public readonly UpdateProduct $updateProduct;

    public readonly DeactivateProduct $deactivateProduct;

    public readonly ReactivateProduct $reactivateProduct;

    public readonly DeleteUnusedProduct $deleteUnusedProduct;

    public readonly ListProductsOfProducer $listProductsOfProducer;

    public readonly ListProducerProducts $listProducerProducts;

    public readonly GetOrderableProduct $getOrderableProduct;

    public readonly ListOrderableProducts $listOrderableProducts;

    public readonly ListProducerProductsForReception $listProducerProductsForReception;

    public readonly GetProducerCutoffRule $getProducerCutoffRule;

    public readonly Actor $manager;

    public function __construct(string $now = '2026-10-22 10:00')
    {
        $this->clock = new FrozenClock($now);
        $this->producers = new InMemoryProducerRepository;
        $this->products = new InMemoryProductRepository;
        $this->cycles = new FakeProducerCycles;
        $this->access = new FakeProducerAccess;
        $transactions = new ImmediateTransactionRunner;
        $this->transactions = $transactions;
        $this->usage = new FakeProductUsage;

        $this->createProducer = new CreateProducer($this->producers);
        $this->deactivateProducer = new DeactivateProducer($this->producers, $this->access, $this->cycles, $transactions);
        $this->reactivateProducer = new ReactivateProducer($this->producers, $this->access, $transactions);
        $this->listProducers = new ListProducers($this->producers);
        $this->getProducer = new GetProducer($this->producers);
        $this->setDefaultCutoff = new SetDefaultCutoff($this->producers);
        $this->getDefaultCutoff = new GetDefaultCutoff($this->producers);

        $this->createProduct = new CreateProduct($this->products);
        $this->updateProduct = new UpdateProduct($this->products, $this->cycles, $transactions);
        $this->deactivateProduct = new DeactivateProduct($this->products, $this->transactions);
        $this->reactivateProduct = new ReactivateProduct($this->products, $this->transactions);
        $this->deleteUnusedProduct = new DeleteUnusedProduct($this->products, $this->usage, $this->transactions);
        $this->listProductsOfProducer = new ListProductsOfProducer($this->products, $this->usage);
        $this->listProducerProducts = new ListProducerProducts($this->products);
        $this->getOrderableProduct = new GetOrderableProduct($this->products, $this->producers);
        $this->listOrderableProducts = new ListOrderableProducts($this->products, $this->producers);
        $this->listProducerProductsForReception = new ListProducerProductsForReception($this->products);
        $this->getProducerCutoffRule = new GetProducerCutoffRule($this->producers);

        $this->manager = Actor::manager('00000000-0000-7000-8000-00000000000a');
    }

    public function catalogueActor(?string $producerId = null): Actor
    {
        return new Actor('00000000-0000-7000-8000-0000000000cc', [Role::Catalogue], producerId: $producerId);
    }

    public function producerActor(string $producerId): Actor
    {
        return Actor::producer('00000000-0000-7000-8000-0000000000aa', $producerId);
    }
}
