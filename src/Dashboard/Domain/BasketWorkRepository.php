<?php

namespace Guixa\Dashboard\Domain;

use Guixa\Dashboard\Domain\ValueObject\BasketWork;

/** Takes explicit scopes and no actor: the caller has already authorised them. */
interface BasketWorkRepository
{
    /** @return list<BasketWork> */
    public function memberCollection(string $householdId): array;

    /** @return list<BasketWork> every household's, inactive ones included */
    public function staffCollection(): array;

    /** @return list<BasketWork> every household's draft staff can prepare or validate */
    public function staffDrafts(): array;
}
