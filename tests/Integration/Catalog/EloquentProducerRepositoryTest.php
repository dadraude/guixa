<?php

namespace Tests\Integration\Catalog;

use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Catalog\Infrastructure\Persistence\EloquentProducerRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Contracts\ProducerRepositoryContract;
use Tests\TestCase;

final class EloquentProducerRepositoryTest extends TestCase
{
    use ProducerRepositoryContract;
    use RefreshDatabase;

    protected function repository(): ProducerRepository
    {
        return new EloquentProducerRepository;
    }

    #[Test]
    public function the_database_refuses_an_account_pointing_at_an_unknown_producer(): void
    {
        $this->assertThrows(fn () => DB::table('users')->insert([
            'id' => (string) Str::uuid7(), 'name' => 'Baker', 'email' => 'baker@example.org', 'password' => 'x',
            'producer_id' => (string) Str::uuid7(),
        ]), QueryException::class);
    }

    #[Test]
    public function the_database_refuses_two_accounts_for_one_producer(): void
    {
        $producers = new EloquentProducerRepository;
        $producer = $this->producer($producers);
        $producers->add($producer);
        $insert = fn (string $email) => DB::table('users')->insert([
            'id' => (string) Str::uuid7(), 'name' => 'Baker', 'email' => $email, 'password' => 'x',
            'producer_id' => $producer->id,
        ]);

        $insert('one@example.org');

        $this->assertThrows(fn () => $insert('two@example.org'), QueryException::class);
    }
}
