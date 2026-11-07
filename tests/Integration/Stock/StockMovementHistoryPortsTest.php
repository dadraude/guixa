<?php

namespace Tests\Integration\Stock;

use App\Models\User;
use DateTimeImmutable;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Application\ListStockMovements;
use Guixa\Stock\Domain\AccountNames;
use Guixa\Stock\Domain\CyclePickupDates;
use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\StockMovementRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StockMovementHistoryPortsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_names_the_authors_membership_stores_deactivated_accounts_included(): void
    {
        $active = User::factory()->create(['name' => 'Maria Manager']);
        $deactivated = User::factory()->create(['name' => 'Dan Departed']);
        DB::table('users')->where('id', $deactivated->id)->update(['deactivated_at' => now()]);

        $names = $this->app->make(AccountNames::class)->forAccounts([$active->id, $deactivated->id, '00000000-0000-7000-8000-0000000000ff']);

        $this->assertSame([$active->id => 'Maria Manager', $deactivated->id => 'Dan Departed'], $names);
    }

    #[Test]
    public function it_answers_the_pickup_date_of_the_cycles_ordering_stores(): void
    {
        DB::table('cycles')->insert(['id' => '00000000-0000-7000-8000-0000000000c1', 'pickup_date' => '2026-11-07']);

        $dates = $this->app->make(CyclePickupDates::class)->forCycles(['00000000-0000-7000-8000-0000000000c1', '00000000-0000-7000-8000-0000000000ff']);

        $this->assertSame(['00000000-0000-7000-8000-0000000000c1' => '2026-11-07'], $dates);
    }

    #[Test]
    public function it_lists_persisted_movements_with_balances_in_occurrence_then_id_order(): void
    {
        $author = User::factory()->create(['name' => 'Maria Manager']);
        $producer = ProducerModel::query()->create(['name' => 'Beekeeper', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        $productId = ProductModel::query()->create([
            'producer_id' => $producer->id, 'stock' => true,
            'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 650, 'variable' => false, 'active' => true,
        ])->id;
        $movements = $this->app->make(StockMovementRepository::class);
        $at = new DateTimeImmutable('2026-10-22T10:00:00+00:00');
        $movements->add(StockMovement::entry('0198f0a0-0000-7000-8000-00000000000b', $productId, Quantity::fromString('2'), $at, $author->id, 'second by id'));
        $movements->add(StockMovement::entry('0198f0a0-0000-7000-8000-00000000000a', $productId, Quantity::fromString('1'), $at, $author->id, 'first by id'));

        $page = $this->app->make(ListStockMovements::class)(Actor::manager($author->id), $productId);

        $this->assertSame(['second by id', 'first by id'], array_map(fn ($line) => $line->note, $page->lines));
        $this->assertSame(['3.000', '1.000'], array_map(fn ($line) => $line->balanceAfter, $page->lines));
        $this->assertSame('Maria Manager', $page->lines[0]->authorName);
    }
}
