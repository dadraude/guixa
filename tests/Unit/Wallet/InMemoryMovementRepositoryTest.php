<?php

namespace Tests\Unit\Wallet;

use Guixa\Wallet\Domain\MovementRepository;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\MovementRepositoryContract;
use Tests\Support\InMemory\InMemoryMovementRepository;

final class InMemoryMovementRepositoryTest extends TestCase
{
    use MovementRepositoryContract;

    protected function repository(): MovementRepository
    {
        return new InMemoryMovementRepository;
    }

    protected function household(): string
    {
        return (string) Str::uuid7();
    }

    private ?string $account = null;

    protected function account(): string
    {
        return $this->account ??= (string) Str::uuid7();
    }
}
