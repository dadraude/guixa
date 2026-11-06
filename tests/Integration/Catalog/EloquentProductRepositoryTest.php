<?php

namespace Tests\Integration\Catalog;

use Guixa\Catalog\Application\ChangeProductClassification;
use Guixa\Catalog\Application\DeleteUnusedProduct;
use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Catalog\Infrastructure\Persistence\EloquentProductRepository;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Contracts\ProductRepositoryContract;
use Tests\TestCase;

final class EloquentProductRepositoryTest extends TestCase
{
    use ProductRepositoryContract;
    use RefreshDatabase;

    protected function repository(): ProductRepository
    {
        return new EloquentProductRepository;
    }

    protected function newProducerId(): string
    {
        return ProducerModel::query()->create([
            'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
        ])->id;
    }

    #[Test]
    public function it_round_trips_a_product_identity_and_stock_mode_through_catalog(): void
    {
        $repository = $this->repository();
        $producerId = $this->newProducerId();
        $product = Product::create($repository->nextId(), $producerId, 'Honey', 'jar', Money::ofCents(650), false, null, stock: true);

        $repository->add($product);

        $reloaded = $repository->get($product->id);
        $this->assertSame($product->id, $reloaded->id);
        $this->assertSame($producerId, $reloaded->producerId);
        $this->assertTrue($reloaded->isStock());
    }

    #[Test]
    public function the_database_refuses_a_product_of_an_unknown_producer(): void
    {
        $this->assertThrows(fn () => $this->insertRow(['producer_id' => (string) Str::uuid7()]), QueryException::class);
    }

    #[Test]
    public function an_unavailable_but_unused_weekly_product_can_be_deleted_before_or_after_stock_classification(): void
    {
        $repository = $this->repository();
        $producerId = $this->newProducerId();
        $manager = Actor::manager((string) Str::uuid7());
        $producer = Actor::producer((string) Str::uuid7(), $producerId);
        $cycleId = (string) Str::uuid7();
        DB::table('cycles')->insert(['id' => $cycleId, 'pickup_date' => now()->addDays(30)->toDateString()]);

        foreach ([false, true] as $classifyFirst) {
            $product = Product::create($repository->nextId(), $producerId, 'Bread', 'loaf', Money::ofCents(350), false, null);
            $repository->add($product);
            DB::table('product_unavailabilities')->insert(['cycle_id' => $cycleId, 'product_id' => $product->id]);
            if ($classifyFirst) {
                app(ChangeProductClassification::class)($manager, $product->id, true);
            }
            app(DeleteUnusedProduct::class)($classifyFirst ? $manager : $producer, $product->id);
            $this->assertNull($repository->get($product->id));
            $this->assertSame(0, DB::table('product_unavailabilities')->where('product_id', $product->id)->count());
        }
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertRow(array $overrides): void
    {
        DB::table('products')->insert([
            'id' => (string) Str::uuid7(), 'producer_id' => $this->newProducerId(), 'name' => 'Bread', 'unit' => 'loaf',
            'price_cents' => 100, 'variable' => false, 'containers_per_unit' => null, 'active' => true, ...$overrides,
        ]);
    }
}
