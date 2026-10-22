<?php

namespace Tests\Unit\Membership;

use Guixa\Membership\Domain\HouseholdRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\HouseholdRepositoryContract;
use Tests\Support\InMemory\InMemoryHouseholdRepository;

final class InMemoryHouseholdRepositoryTest extends TestCase
{
    use HouseholdRepositoryContract;

    protected function repository(): HouseholdRepository
    {
        return new InMemoryHouseholdRepository;
    }
}
