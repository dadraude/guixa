<?php

namespace Tests\Integration\Sales;

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Infrastructure\Persistence\EloquentBasketRepository;
use Guixa\Shared\Domain\Quantity;
use Guixa\Shared\Domain\UtcTimestamp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    protected function anotherHousehold(): string
    {
        $household = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $household, 'name' => 'Household Other', 'created_at' => now(), 'updated_at' => now()]);

        return $household;
    }

    protected function weeklySource(): array
    {
        $household = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $household, 'name' => 'Household W', 'created_at' => now(), 'updated_at' => now()]);
        $cycle = (string) Str::uuid7();
        DB::table('cycles')->insert(['id' => $cycle, 'pickup_date' => now()->addDays(30)->toDateString()]);
        $order = (string) Str::uuid7();
        DB::table('orders')->insert(['id' => $order, 'cycle_id' => $cycle, 'household_id' => $household]);

        return [$order, $cycle];
    }

    public function test_baskets_carry_no_cycle_reference_and_store_a_weekly_one_with_its_source_order(): void
    {
        $this->assertFalse(Schema::hasColumn('baskets', 'reference_cycle_id'));

        [$orderId] = $this->weeklySource();
        $household = (string) DB::table('orders')->where('id', $orderId)->value('household_id');
        $weekly = fn (?string $source) => fn () => DB::transaction(fn () => DB::table('baskets')->insert([
            'id' => (string) Str::uuid7(), 'household_id' => $household, 'household_name' => 'Household W', 'kind' => 'weekly',
            'state' => 'draft', 'created_by' => null, 'created_at' => UtcTimestamp::toStorage(now()), 'source_order_id' => $source,
        ]));

        $weekly($orderId)();
        $this->assertSame(1, DB::table('baskets')->where('source_order_id', $orderId)->count());
    }

    public function test_list_loads_all_basket_lines_adjustments_and_collection_events_in_four_queries_without_losing_snapshots(): void
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
            $repository->save(new Basket($id, $household, 'Household K', BasketState::Draft, $manager,
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

        $this->assertSame(4, $queryCount);
        $this->assertSame(array_reverse($ids), array_map(fn (Basket $basket) => $basket->id, $listed));
        $this->assertSame([], $listed[0]->lines);
        $this->assertSame('1.125', $listed[1]->lines[0]->quantity->toString());
        $this->assertSame(1201, $listed[1]->lines[0]->priceCents);
        $this->assertSame(['Earlier oil', 'Oil'], array_map(fn (BasketLine $line) => $line->name, $listed[2]->lines));
        $this->assertNotNull($listed[2]->lines[0]->removedAt);
        $this->assertSame([], $listed[2]->activeLines());
    }

    public function test_settled_read_uses_a_fixed_number_of_queries_whatever_the_number_of_households(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $other = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $other, 'name' => 'Household O', 'created_at' => now(), 'updated_at' => now()]);
        $when = new \DateTimeImmutable('2026-10-30T10:00:00+00:00');
        foreach ([$household, $other, $household] as $owner) {
            $id = $repository->nextId();
            $line = new BasketLine($repository->nextId(), $id, $product, 'Oil', 'bottle', 1200, false, '1', Quantity::fromString('1'), $when);
            $repository->save(new Basket($id, $owner, 'Household', BasketState::Draft, $manager, $when, [$line]));
            $repository->save($repository->get($id)->validated($manager, $when));
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $settled = $repository->settledForHouseholds([$household, $other]);
            $queryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertCount(3, $settled);
        // Baskets, lines, adjustments and collection events; adjustment lines load only when an adjustment exists.
        $this->assertSame(4, $queryCount);
    }
}
