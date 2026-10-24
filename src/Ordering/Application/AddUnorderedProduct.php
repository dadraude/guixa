<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\FractionalQuantityNotAllowed;
use Guixa\Ordering\Domain\Error\ProductAlreadyOrdered;
use Guixa\Ordering\Domain\Error\ProductNotOrderable;
use Guixa\Ordering\Domain\ReceivedTotal;
use Guixa\Ordering\Domain\ReceivedTotalRepository;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Quantity;

/**
 * Adds a product of the producer that nobody ordered, active or not (the goods have arrived) to its reception, with what was received of it (R14, S40).
 * Adding it again replaces the total, which is how a manager corrects it: way A cannot split a product nobody ordered (R15).
 */
final class AddUnorderedProduct
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly ReceptionLoader $loader,
        private readonly ReceivedTotalRepository $receivedTotals,
        private readonly TransactionRunner $transactions,
    ) {}

    /** @param  string  $received  at most 3 decimals; whole units for a non-variable product */
    public function __invoke(Actor $actor, string $cycleId, string $producerId, string $productId, string $received = '0'): void
    {
        $actor->assertManager();

        $total = Quantity::fromString($received);

        $this->transactions->run(function () use ($cycleId, $producerId, $productId, $total) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $loaded = $this->loader->load($cycle, $producerId);

            $product = $loaded->catalog[$productId] ?? throw ProductNotOrderable::withId($productId);

            if (! $product->variable && ! $total->isWhole()) {
                throw FractionalQuantityNotAllowed::forUnit($product->name, $total->toString());
            }

            $inReception = $loaded->reception->product($productId);

            // R14 only adds a product nobody ordered; one somebody ordered is already there and follows way A.
            if ($inReception !== null && ! $inReception->nobodyOrdered()) {
                throw ProductAlreadyOrdered::withId($productId);
            }

            $this->receivedTotals->set(new ReceivedTotal($cycleId, $producerId, $productId, $total));
        });
    }
}
