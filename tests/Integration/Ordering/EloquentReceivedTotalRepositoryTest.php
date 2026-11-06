<?php

namespace Tests\Integration\Ordering;

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Ordering\Domain\ReceivedTotalRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentReceivedTotalRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Contracts\ReceivedTotalRepositoryContract;
use Tests\TestCase;

final class EloquentReceivedTotalRepositoryTest extends TestCase
{
    use ReceivedTotalRepositoryContract;
    use RefreshDatabase;

    protected function repository(): ReceivedTotalRepository
    {
        return new EloquentReceivedTotalRepository;
    }

    protected function referencedRowsExist(): void
    {
        foreach ([self::CYCLE_A => '2026-11-07', self::CYCLE_B => '2026-11-14'] as $id => $pickupDate) {
            DB::table('cycles')->insertOrIgnore(['id' => $id, 'pickup_date' => $pickupDate]);
        }

        foreach ([self::BAKER => [self::BREAD, 'Baker'], self::GROWER => [self::APPLES, 'Grower']] as $producerId => [$productId, $name]) {
            ProducerModel::query()->firstOrCreate(['id' => $producerId], [
                'name' => $name, 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
            ]);
            DB::table('products')->insertOrIgnore([
                'id' => $productId, 'producer_id' => $producerId, 'name' => "Product {$productId}", 'unit' => 'unit', 'price_cents' => 100, 'variable' => false,
            ]);
        }
    }
}
