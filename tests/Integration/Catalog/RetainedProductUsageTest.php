<?php

namespace Tests\Integration\Catalog;

use App\Models\User;
use Guixa\Catalog\Application\ChangeProductClassification;
use Guixa\Catalog\Application\DeleteUnusedProduct;
use Guixa\Catalog\Domain\Error\ProductUsed;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Ordering\Application\AddUnorderedProduct;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\UtcTimestamp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The Catalog guards are exercised through the real RetainedProductUsage wiring, with no fake ProductUsage. */
final class RetainedProductUsageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, ?string}> */
    public static function basketLines(): array
    {
        return [
            'an active draft line' => ['draft', null],
            'a removed draft line' => ['draft', '2026-11-01 10:00:00'],
            'a line of a cancelled basket' => ['cancelled', null],
        ];
    }

    #[Test]
    #[DataProvider('basketLines')]
    public function a_product_referenced_only_by_a_basket_line_cannot_be_reclassified_or_deleted_s87(string $basketState, ?string $removedAt): void
    {
        $producer = $this->producer();
        $product = $this->product($producer, stock: true);
        $this->basketLine($product, $basketState, $removedAt);
        $this->assertSame(0, DB::table('stock_movements')->where('product_id', $product)->count());
        $this->assertSame(0, DB::table('order_lines')->where('product_id', $product)->count());
        $manager = Actor::manager((string) Str::uuid7());

        foreach ([
            'to weekly' => fn () => app(ChangeProductClassification::class)($manager, $product, false),
            'delete' => fn () => app(DeleteUnusedProduct::class)($manager, $product),
        ] as $operation => $attempt) {
            try {
                $attempt();
                $this->fail("{$operation} must be refused");
            } catch (ProductUsed) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertTrue(ProductModel::query()->find($product)->stock);
    }

    #[Test]
    public function a_stock_product_with_no_reference_can_still_be_reclassified_and_deleted_s87(): void
    {
        $producer = $this->producer();
        [$reclassified, $deleted] = [$this->product($producer, stock: true), $this->product($producer, stock: true)];
        $manager = Actor::manager((string) Str::uuid7());

        app(ChangeProductClassification::class)($manager, $reclassified, false);
        app(DeleteUnusedProduct::class)($manager, $deleted);

        $this->assertFalse(ProductModel::query()->find($reclassified)->stock);
        $this->assertFalse(ProductModel::query()->whereKey($deleted)->exists());
    }

    #[Test]
    public function a_product_referenced_by_a_reception_cannot_be_reclassified_or_deleted_s69(): void
    {
        $producer = $this->producer();
        $product = $this->product($producer, stock: false);
        $cycle = (string) Str::uuid7();
        DB::table('cycles')->insert(['id' => $cycle, 'pickup_date' => now()->addDays(30)->toDateString()]);
        DB::table('cycle_producers')->insert(['cycle_id' => $cycle, 'producer_id' => $producer, 'participating' => true, 'closes_at' => null, 'sent_at' => UtcTimestamp::toStorage(now())]);
        $manager = Actor::manager((string) Str::uuid7());

        app(AddUnorderedProduct::class)($manager, $cycle, $producer, $product, '0');

        $this->assertSame(1, DB::table('received_totals')->where('product_id', $product)->count());
        $this->assertSame(0, DB::table('order_lines')->where('product_id', $product)->count());
        $this->assertThrows(fn () => app(ChangeProductClassification::class)($manager, $product, true), ProductUsed::class);
        $this->assertThrows(fn () => app(DeleteUnusedProduct::class)(Actor::producer((string) Str::uuid7(), $producer), $product), ProductUsed::class);
        $this->assertFalse(ProductModel::query()->find($product)->stock);
        $this->assertTrue(ProductModel::query()->whereKey($product)->exists());
    }

    private function producer(): string
    {
        $producer = (string) Str::uuid7();
        DB::table('producers')->insert(['id' => $producer, 'name' => 'Beekeeper', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);

        return $producer;
    }

    private function product(string $producer, bool $stock): string
    {
        $product = (string) Str::uuid7();
        DB::table('products')->insert(['id' => $product, 'producer_id' => $producer, 'name' => 'Honey', 'unit' => 'unit', 'price_cents' => 350, 'variable' => false, 'active' => true, 'stock' => $stock]);

        return $product;
    }

    private function basketLine(string $product, string $basketState, ?string $removedAt): void
    {
        $household = (string) Str::uuid7();
        $basket = (string) Str::uuid7();
        $creator = User::factory()->create()->id;
        DB::table('households')->insert(['id' => $household, 'name' => 'H', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('baskets')->insert([
            'id' => $basket, 'household_id' => $household, 'household_name' => 'H', 'kind' => 'stock', 'state' => $basketState,
            'created_by' => $creator, 'created_at' => UtcTimestamp::toStorage(now()), 'cancelled_by' => $basketState === 'cancelled' ? $creator : null,
            'cancelled_at' => $basketState === 'cancelled' ? UtcTimestamp::toStorage(now()) : null,
        ]);
        DB::table('basket_lines')->insert([
            'id' => (string) Str::uuid7(), 'basket_id' => $basket, 'kind' => 'stock', 'product_id' => $product, 'name' => 'Honey', 'unit' => 'unit',
            'price_cents' => 350, 'variable' => false, 'quantity' => '1', 'created_at' => UtcTimestamp::toStorage(now()), 'removed_at' => $removedAt,
        ]);
    }
}
