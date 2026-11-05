<?php

namespace Tests\Support\InMemory\Sales;

use DomainException;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketCollectionEvent;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketLineKind;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\BasketState;
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
        $corrected = $prior?->state === BasketState::Collected && $basket->state === BasketState::Validated
            && count($basket->collectionEvents) === count($prior->collectionEvents) + 1
            && $basket->collectionEvents[array_key_last($basket->collectionEvents)]->type === 'corrected';
        if ($prior !== null) {
            $appendedEvents = count($basket->collectionEvents) - count($prior->collectionEvents);
            $collected = $prior->state === BasketState::Validated && $basket->state === BasketState::Collected
                && (($prior->collectedAt === null && $appendedEvents <= 1)
                    || ($prior->collectedAt !== null && $appendedEvents === 1))
                && ($appendedEvents === 0 || $basket->collectionEvents[array_key_last($basket->collectionEvents)]->type === 'collected');
            if (! $corrected && ! $collected && $appendedEvents !== 0) {
                throw BasketNotEditable::create();
            }
        }
        if ($prior !== null && in_array($prior->state, [BasketState::Collected, BasketState::Cancelled], true)
            && ((! $corrected && $prior->state !== $basket->state) || $prior->lines !== $basket->lines
                || $prior->validatedTotalCents !== $basket->validatedTotalCents)) {
            throw BasketNotEditable::create();
        }
        if ($prior?->state === BasketState::Validated && (! $basket->state->isSettled() || $prior->lines !== $basket->lines
            || $prior->validatedTotalCents !== $basket->validatedTotalCents)) {
            throw BasketNotEditable::create();
        }
        if ($prior !== null && count($basket->adjustments) < count($prior->adjustments)) {
            throw BasketNotEditable::create();
        }
        foreach ($prior?->adjustments ?? [] as $index => $adjustment) {
            $candidate = $basket->adjustments[$index];
            if (! $adjustment->matches($candidate) || $adjustment->occurredAt != $candidate->occurredAt) {
                throw BasketNotEditable::create();
            }
        }
        if ($prior !== null && count($basket->collectionEvents) < count($prior->collectionEvents)) {
            throw BasketNotEditable::create();
        }
        foreach ($prior?->collectionEvents ?? [] as $index => $event) {
            $candidate = $basket->collectionEvents[$index];
            if (! $event->matches($candidate) || $event->occurredAt != $candidate->occurredAt) {
                throw BasketNotEditable::create();
            }
        }
        foreach ($basket->collectionEvents as $event) {
            foreach ($this->baskets as $storedBasket) {
                foreach ($storedBasket->collectionEvents as $storedEvent) {
                    if ($storedEvent->id === $event->id && $storedBasket->id !== $basket->id) {
                        throw BasketNotEditable::create();
                    }
                }
            }
        }
        $active = [];
        foreach ($basket->activeLines() as $line) {
            $key = $line->kind->value.':'.($line->kind === BasketLineKind::Return ? $line->returnItemId : $line->productId);
            if (isset($active[$key])) {
                throw new DomainException('Duplicate active basket line.');
            }
            $active[$key] = true;
        }
        foreach ($this->baskets as $storedBasket) {
            $sameSource = $basket->sourceOrderId !== null && $storedBasket->id !== $basket->id
                && $storedBasket->sourceOrderId === $basket->sourceOrderId;
            $isBase = ! $basket->late && ! $storedBasket->late;
            $bothOpenLate = $basket->late && $storedBasket->late && $basket->state->followsSource() && $storedBasket->state->followsSource();
            if ($sameSource && ($isBase || $bothOpenLate)) {
                throw new DomainException('Duplicate weekly source basket.');
            }
        }
        if ($basket->state === BasketState::Collected && $basket->collectedAt !== null && $basket->collectionEvents === []) {
            $basket = new Basket($basket->id, $basket->householdId, $basket->householdName, $basket->state,
                $basket->createdBy, $basket->createdAt, $basket->lines, $basket->validatedBy, $basket->validatedAt,
                $basket->validatedTotalCents, $basket->collectedBy, $basket->collectedAt, $basket->cancelledBy,
                $basket->cancelledAt, $basket->adjustments,
                [new BasketCollectionEvent($this->nextId(), $basket->id, 'collected', $basket->collectedBy, $basket->collectedAt)],
                $basket->kind, $basket->sourceOrderId, $basket->referenceCycleId, $basket->late);
        }
        $this->baskets[$basket->id] = $basket;
    }

    public function get(string $id): ?Basket
    {
        return $this->baskets[$id] ?? null;
    }

    public function getBySourceOrder(string $orderId): ?Basket
    {
        foreach ($this->baskets as $basket) {
            if ($basket->sourceOrderId === $orderId && ! $basket->late) {
                return $basket;
            }
        }

        return null;
    }

    public function getOpenLateBySourceOrder(string $orderId): ?Basket
    {
        foreach ($this->baskets as $basket) {
            if ($basket->sourceOrderId === $orderId && $basket->late && $basket->state->followsSource()) {
                return $basket;
            }
        }

        return null;
    }

    public function weeklyOfCycle(string $cycleId): array
    {
        return array_values(array_filter($this->baskets, fn (Basket $basket) => $basket->kind === BasketKind::Weekly && $basket->referenceCycleId === $cycleId));
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

    public function settledForHouseholds(array $householdIds): array
    {
        return array_values(array_filter($this->baskets,
            fn (Basket $basket) => in_array($basket->householdId, $householdIds, true) && $basket->state->isSettled()));
    }

    public function pendingCollections(?string $householdId = null): array
    {
        $pending = array_filter($this->baskets, fn (Basket $basket) => $basket->isPendingCollection()
            && ($householdId === null || $basket->householdId === $householdId));
        usort($pending, fn (Basket $a, Basket $b) => [$a->validatedAt, $a->id] <=> [$b->validatedAt, $b->id]);

        return array_values($pending);
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
