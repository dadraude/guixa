<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Sales\Domain\Error\InvalidBasketQuantity;
use Guixa\Sales\Domain\Error\NotEnoughBasketStock;
use Guixa\Sales\Domain\Error\StockProductUnavailable;
use Guixa\Sales\Domain\StockProducts;
use Guixa\Sales\Domain\StockQuantities;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Quantity;

final class AddStockToBasket
{
    public function __construct(private readonly BasketRepository $baskets, private readonly StockProducts $products,
        private readonly StockQuantities $quantities, private readonly TransactionRunner $transactions, private readonly Clock $clock) {}

    public function __invoke(Actor $actor, string $basketId, string $productId, string $quantity): void
    {
        $actor->assertCanManageSales();
        $addition = Quantity::fromString($quantity);
        if ($addition->isZero()) {
            throw InvalidBasketQuantity::create();
        }

        $this->transactions->run(function () use ($basketId, $productId, $addition): void {
            $basket = $this->baskets->lock($basketId) ?? throw BasketNotFound::withId($basketId);
            if ($basket->state !== 'draft') {
                throw BasketNotEditable::create();
            }
            $product = $this->products->lock($productId);
            if ($product === null || ! $product->active) {
                throw StockProductUnavailable::create();
            }
            $existing = $basket->activeLineFor($productId);
            $combined = Quantity::ofThousandths($addition->thousandths + ($existing?->quantity->thousandths ?? 0));
            if (! $product->variable && ! $combined->isWhole()) {
                throw InvalidBasketQuantity::create();
            }
            $available = Quantity::fromString($this->quantities->forProducts([$productId])[$productId] ?? '0.000');
            if ($combined->thousandths > $available->thousandths) {
                throw NotEnoughBasketStock::for($available->toString());
            }

            $lines = $basket->lines;
            if ($existing !== null) {
                foreach ($lines as $index => $line) {
                    if ($line->id === $existing->id) {
                        $lines[$index] = $line->withQuantity($combined);
                    }
                }
            } else {
                $lines[] = new BasketLine($this->baskets->nextId(), $basketId, $productId, $product->name,
                    $product->unit, $product->priceCents, $product->variable, $product->containersPerUnit,
                    $combined, $this->clock->now());
            }
            $this->baskets->save($basket->withLines($lines));
        });
    }
}
