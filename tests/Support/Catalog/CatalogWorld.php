<?php

namespace Tests\Support\Catalog;

use Guixa\Catalog\Application\CreateProducer;
use Guixa\Catalog\Application\GetDefaultCutoff;
use Guixa\Catalog\Application\GetProducer;
use Guixa\Catalog\Application\ListProducers;
use Guixa\Catalog\Application\SetDefaultCutoff;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\InMemoryProducerRepository;

/** The Catalog use cases wired to in-memory adapters, for the Application tests. */
final class CatalogWorld
{
    public readonly FrozenClock $clock;

    public readonly InMemoryProducerRepository $producers;

    public readonly CreateProducer $createProducer;

    public readonly ListProducers $listProducers;

    public readonly GetProducer $getProducer;

    public readonly SetDefaultCutoff $setDefaultCutoff;

    public readonly GetDefaultCutoff $getDefaultCutoff;

    public readonly Actor $manager;

    public function __construct(string $now = '2026-10-22 10:00')
    {
        $this->clock = new FrozenClock($now);
        $this->producers = new InMemoryProducerRepository;

        $this->createProducer = new CreateProducer($this->producers);
        $this->listProducers = new ListProducers($this->producers);
        $this->getProducer = new GetProducer($this->producers);
        $this->setDefaultCutoff = new SetDefaultCutoff($this->producers);
        $this->getDefaultCutoff = new GetDefaultCutoff($this->producers);

        $this->manager = new Actor('00000000-0000-7000-8000-00000000000a', [Role::Manager]);
    }

    public function producerActor(string $producerId): Actor
    {
        return new Actor('00000000-0000-7000-8000-0000000000aa', [Role::Producer], null, $producerId);
    }
}
