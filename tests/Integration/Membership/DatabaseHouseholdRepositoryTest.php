<?php

namespace Tests\Integration\Membership;

use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Membership\Infrastructure\Persistence\DatabaseHouseholdRepository;
use Guixa\Shared\Infrastructure\PostgresLocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Contracts\HouseholdRepositoryContract;
use Tests\TestCase;

final class DatabaseHouseholdRepositoryTest extends TestCase
{
    use HouseholdRepositoryContract;
    use RefreshDatabase;

    protected function repository(): HouseholdRepository
    {
        return new DatabaseHouseholdRepository(new PostgresLocks);
    }
}
