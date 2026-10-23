<?php

namespace Tests\Unit\Membership;

use Guixa\Membership\Domain\AccountRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\AccountRepositoryContract;
use Tests\Support\InMemory\InMemoryAccountRepository;

final class InMemoryAccountRepositoryTest extends TestCase
{
    use AccountRepositoryContract;

    protected function repository(): AccountRepository
    {
        return new InMemoryAccountRepository;
    }

    protected function householdId(): string
    {
        return '00000000-0000-7000-8000-00000000000c';
    }

    protected function producerId(): string
    {
        return '00000000-0000-7000-8000-0000000000b1';
    }
}
