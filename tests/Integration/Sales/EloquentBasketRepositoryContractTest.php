<?php

namespace Tests\Integration\Sales;

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Infrastructure\Persistence\EloquentBasketRepository;
use Guixa\Shared\Domain\Quantity;
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

        $returnA = (string) Str::uuid7();
        $returnB = (string) Str::uuid7();
        DB::table('return_items')->insert([
            ['id' => $returnA, 'name' => 'Glass jar', 'amount_cents' => 50],
            ['id' => $returnB, 'name' => 'Bottle', 'amount_cents' => 25],
        ]);

        return [new EloquentBasketRepository, $manager, $household, $product, $returnA, $returnB];
    }

    public function test_list_loads_all_basket_lines_and_adjustments_in_three_queries_without_losing_snapshots(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $created = new \DateTimeImmutable('2026-10-30T10:00:00+00:00');
        $removed = new \DateTimeImmutable('2026-10-30T11:00:00+00:00');
        $ids = [];

        foreach ([0, 1, 2] as $index) {
            $id = $repository->nextId();
            $ids[] = $id;
            $lines = $index === 2 ? [] : [new BasketLine($repository->nextId(), $id, $product,
                'Oil', 'bottle', 1200 + $index, false, null, Quantity::fromString('1.125'),
                $created, $index === 0 ? $removed : null)];
            if ($index === 0) {
                $lines[] = new BasketLine($repository->nextId(), $id, $product,
                    'Earlier oil', 'bottle', 1199, false, null, Quantity::fromString('0.125'),
                    $created->modify('-1 hour'), $removed);
            }
            $repository->save(new Basket($id, $household, 'Household K', 'draft', $manager,
                $created->modify("+{$index} day"), $lines));
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $listed = $repository->list();
            $queryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertSame(3, $queryCount);
        $this->assertSame(array_reverse($ids), array_map(fn (Basket $basket) => $basket->id, $listed));
        $this->assertSame([], $listed[0]->lines);
        $this->assertSame('1.125', $listed[1]->lines[0]->quantity->toString());
        $this->assertSame(1201, $listed[1]->lines[0]->priceCents);
        $this->assertSame(['Earlier oil', 'Oil'], array_map(fn (BasketLine $line) => $line->name, $listed[2]->lines));
        $this->assertNotNull($listed[2]->lines[0]->removedAt);
        $this->assertSame([], $listed[2]->activeLines());
    }
}
