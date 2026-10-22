<?php

namespace Tests\Support\InMemory;

use Illuminate\Support\Str;

/**
 * Base for the in-memory repositories the Application tests use instead of Eloquent ones.
 *
 * Aggregates are kept by id. Concrete repositories implement their domain port on top of
 * `save`, `find`, `remove` and `all`, and must pass the same contract test as the Eloquent
 * repository (see Tests\Support\Contracts).
 *
 * Like a database, it stores and returns copies: changing a fetched aggregate does nothing until it is
 * saved again. `clone` is shallow, so an aggregate holding a nested mutable object must deep-clone itself
 * in `__clone`.
 *
 * @template T of object
 */
abstract class InMemoryRepository
{
    /** @var array<string, T> */
    private array $items = [];

    /** Ids are UUID v7, like the ones the Eloquent repositories generate. */
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    /** @param T $item */
    protected function save(object $item): void
    {
        $this->items[$this->idOf($item)] = clone $item;
    }

    /** @return T|null */
    protected function find(string $id): ?object
    {
        return isset($this->items[$id]) ? clone $this->items[$id] : null;
    }

    protected function remove(string $id): void
    {
        unset($this->items[$id]);
    }

    /** @return list<T> */
    protected function all(): array
    {
        return array_map(fn (object $item) => clone $item, array_values($this->items));
    }

    /** @param T $item */
    abstract protected function idOf(object $item): string;
}
