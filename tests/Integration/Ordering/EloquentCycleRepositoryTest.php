<?php

namespace Tests\Integration\Ordering;

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Ordering\Domain\Cycle;
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

    #[Test]
    public function list_loads_all_producers_in_two_queries_with_their_saved_state(): void
    {
        $producerA = (string) Str::uuid7();
        $producerB = (string) Str::uuid7();
        [$producerA, $producerB] = collect([$producerA, $producerB])->sort()->values()->all();
        $this->producersExist($producerA, $producerB);
        $cycleIds = [];

        foreach (['2026-11-07', '2026-11-14', '2026-11-21'] as $date) {
            $id = (string) Str::uuid7();
            $cycleIds[] = $id;
            DB::table('cycles')->insert(['id' => $id, 'pickup_date' => $date]);
        }

        DB::table('cycle_producers')->insert([
            ['cycle_id' => $cycleIds[0], 'producer_id' => $producerB, 'participating' => false,
                'closes_at' => '2026-11-04 20:00:00+00', 'sent_at' => '2026-11-05 09:00:00+00',
                'validated_at' => '2026-11-06 10:00:00+00', 'distributed_at' => '2026-11-07 11:00:00+00'],
            ['cycle_id' => $cycleIds[0], 'producer_id' => $producerA, 'participating' => true,
                'closes_at' => null, 'sent_at' => null, 'validated_at' => null, 'distributed_at' => null],
            ['cycle_id' => $cycleIds[1], 'producer_id' => $producerA, 'participating' => true,
                'closes_at' => null, 'sent_at' => null, 'validated_at' => null, 'distributed_at' => null],
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $cycles = $this->repository()->list();
            $queryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertSame(2, $queryCount);
        $this->assertSame($cycleIds, array_map(fn (Cycle $cycle) => $cycle->id, $cycles));
        $this->assertSame([$producerA, $producerB], array_map(fn ($order) => $order->producerId, $cycles[0]->producerOrders()));
        $this->assertTrue($cycles[0]->isParticipating($producerA));
        $this->assertFalse($cycles[0]->isParticipating($producerB));
        $this->assertSame('2026-11-04 20:00:00', $cycles[0]->producerOrders()[1]->cutoff()?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-05 09:00:00', $cycles[0]->producerOrders()[1]->sentAt()?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-06 10:00:00', $cycles[0]->producerOrders()[1]->validatedAt()?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-07 11:00:00', $cycles[0]->producerOrders()[1]->distributedAt()?->format('Y-m-d H:i:s'));
        $this->assertSame([], $cycles[2]->producerOrders());
    }
}
