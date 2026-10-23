<?php

namespace Tests\Integration\Ordering;

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentProductAvailabilityRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Contracts\ProductAvailabilityRepositoryContract;
use Tests\TestCase;

final class EloquentProductAvailabilityRepositoryTest extends TestCase
{
    use ProductAvailabilityRepositoryContract;
    use RefreshDatabase;

    protected function repository(): ProductAvailabilityRepository
    {
        return new EloquentProductAvailabilityRepository;
    }

    protected function referencedRowsExist(): void
    {
        foreach ([self::CYCLE_A => '2026-11-07', self::CYCLE_B => '2026-11-14'] as $id => $pickupDate) {
            DB::table('cycles')->insertOrIgnore(['id' => $id, 'pickup_date' => $pickupDate]);
        }

        $baker = ProducerModel::query()->firstOrCreate(['name' => 'Baker'], [
            'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
        ]);

        foreach ([self::JAM, self::BREAD] as $id) {
            DB::table('products')->insertOrIgnore([
                'id' => $id, 'producer_id' => $baker->id, 'name' => "Product {$id}", 'unit' => 'unit', 'price_cents' => 100, 'variable' => false,
            ]);
        }
    }

    #[Test]
    public function the_database_refuses_a_mark_for_an_unknown_cycle(): void
    {
        $this->referencedRowsExist();

        $this->assertThrows(
            fn () => $this->repository()->markUnavailable('00000000-0000-7000-8000-0000000000fe', self::JAM),
            QueryException::class,
        );
    }
}
