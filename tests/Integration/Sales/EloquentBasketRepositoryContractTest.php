<?php

namespace Tests\Integration\Sales;

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Infrastructure\Persistence\EloquentBasketRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Contracts\BasketRepositoryContract;
use Tests\TestCase;

final class EloquentBasketRepositoryContractTest extends TestCase
{
    use BasketRepositoryContract;
    use RefreshDatabase;

    protected function basketWorld(): array
    {
        $manager = User::factory()->create()->id;
        $household = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $household, 'name' => 'Household K', 'created_at' => now(), 'updated_at' => now()]);
        $producer = ProducerModel::query()->create(['name' => 'Supplier', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        $product = (string) Str::uuid7();
        DB::table('products')->insert(['id' => $product, 'producer_id' => $producer->id,
            'stock' => true, 'name' => 'Oil', 'unit' => 'bottle', 'price_cents' => 1200,
            'variable' => false, 'active' => true]);

        return [new EloquentBasketRepository, $manager, $household, $product];
    }
}
