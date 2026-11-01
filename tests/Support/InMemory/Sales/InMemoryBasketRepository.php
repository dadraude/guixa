<?php

namespace Tests\Support\InMemory\Sales;

use DomainException;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotEditable;

final class InMemoryBasketRepository implements BasketRepository
{
    /** @var array<string,Basket> */
    private array $baskets = [];

    private int $sequence = 0;

    public function nextId(): string
    {
        return 'basket-'.++$this->sequence;
    }

    public function save(Basket $basket): void
    {
        $prior = $this->baskets[$basket->id] ?? null;
        if ($prior !== null && in_array($prior->state, ['validated', 'collected', 'cancelled'], true)
            && $prior->state !== 'validated') {
            throw BasketNotEditable::create();
        }
        if ($prior?->state === 'validated' && ($basket->state !== 'collected' || $prior->lines !== $basket->lines
            || $prior->validatedTotalCents !== $basket->validatedTotalCents)) {
            throw BasketNotEditable::create();
        }
        $active = [];
        foreach ($basket->activeLines() as $line) {
            $key = $line->kind.':'.($line->kind === 'return' ? $line->returnItemId : $line->productId);
            if (isset($active[$key])) {
                throw new DomainException('Duplicate active basket line.');
            }
            $active[$key] = true;
        }
        $this->baskets[$basket->id] = $basket;
    }

    public function get(string $id): ?Basket
    {
        return $this->baskets[$id] ?? null;
    }

    public function lock(string $id): ?Basket
    {
        return $this->get($id);
    }

    public function list(): array
    {
        return array_values($this->baskets);
    }

    public function getForHousehold(string $id, string $householdId): ?Basket
    {
        $basket = $this->get($id);

        return $basket?->householdId === $householdId ? $basket : null;
    }

    public function listForHousehold(string $householdId): array
    {
        return array_values(array_filter($this->baskets,
            fn (Basket $basket) => $basket->householdId === $householdId));
    }

    public function hasProductHistory(string $productId): bool
    {
        foreach ($this->baskets as $basket) {
            foreach ($basket->lines as $line) {
                if ($line->productId === $productId) {
                    return true;
                }
            }
        }

        return false;
    }
}
