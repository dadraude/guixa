<?php

namespace Tests\Integration\Ordering;

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentCycleRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Contracts\CycleRepositoryContract;
use Tests\TestCase;

final class EloquentCycleRepositoryTest extends TestCase
{
    use CycleRepositoryContract;
    use RefreshDatabase;

    protected function repository(): CycleRepository
    {
        return new EloquentCycleRepository;
    }

    protected function producersExist(string ...$producerIds): void
    {
        foreach ($producerIds as $id) {
            ProducerModel::query()->firstOrCreate(['id' => $id], [
                'name' => "Producer {$id}", 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
            ]);
        }
    }

    private function insertCycle(string $date, ?string $cancelledAt = null): void
    {
        DB::table('cycles')->insert(['id' => (string) Str::uuid7(), 'pickup_date' => $date, 'cancelled_at' => $cancelledAt]);
    }

    #[Test]
    public function the_database_refuses_two_live_cycles_with_one_pickup_date(): void
    {
        $this->insertCycle('2026-11-07');

        $this->assertThrows(fn () => $this->insertCycle('2026-11-07'), QueryException::class);
    }

    #[Test]
    public function a_cancelled_cycle_does_not_take_its_pickup_date(): void
    {
        $this->insertCycle('2026-11-07', '2026-10-30 10:00:00+00');
        $this->insertCycle('2026-11-07');

        $this->assertTrue($this->repository()->existsOnPickupDate(new \DateTimeImmutable('2026-11-07')));
        $this->assertSame(2, DB::table('cycles')->where('pickup_date', '2026-11-07')->count());
    }

    #[Test]
    public function the_pickup_date_ignores_cancelled_cycles_when_asking_whether_one_exists(): void
    {
        $this->insertCycle('2026-11-07', '2026-10-30 10:00:00+00');

        $this->assertFalse($this->repository()->existsOnPickupDate(new \DateTimeImmutable('2026-11-07')));
    }

    #[Test]
    public function the_database_refuses_a_participant_of_an_unknown_cycle(): void
    {
        $this->assertThrows(fn () => DB::table('cycle_producers')->insert([
            'cycle_id' => (string) Str::uuid7(), 'producer_id' => (string) Str::uuid7(), 'participating' => true,
        ]), QueryException::class);
    }
}
