<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The outcome of deactivating or reactivating a household. A stale request on a
 * household already in the wanted state changes nothing and says so, so the
 * person is not told something happened that did not.
 */
final readonly class HouseholdStatusChange
{
    /**
     * @param  list<string>  $skippedNames  Members left as they were because the actor may not change them alone.
     */
    private function __construct(
        public bool $changed,
        public array $skippedNames,
    ) {}

    /**
     * @param  list<string>  $skippedNames
     */
    public static function changed(array $skippedNames = []): self
    {
        return new self(true, $skippedNames);
    }

    public static function alreadyInThatState(): self
    {
        return new self(false, []);
    }
}
