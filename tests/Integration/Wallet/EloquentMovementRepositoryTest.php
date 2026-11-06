<?php

namespace Tests\Integration\Wallet;

use App\Models\User;
use Guixa\Wallet\Domain\MovementRepository;
use Guixa\Wallet\Infrastructure\Persistence\EloquentMovementRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Contracts\MovementRepositoryContract;
use Tests\TestCase;

final class EloquentMovementRepositoryTest extends TestCase
{
    use MovementRepositoryContract;
    use RefreshDatabase;

    private ?string $account = null;

    protected function repository(): MovementRepository
    {
        return new EloquentMovementRepository;
    }

    protected function household(): string
    {
        $id = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $id, 'name' => 'Household '.$id, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    protected function account(): string
    {
        return $this->account ??= User::factory()->create()->id;
    }

    protected function basket(string $household): string
    {
        $id = (string) Str::uuid7();
        DB::table('baskets')->insert([
            'id' => $id, 'household_id' => $household, 'household_name' => 'Household',
            'kind' => 'stock', 'state' => 'draft', 'created_by' => $this->account(),
            'created_at' => now(),
        ]);

        return $id;
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function inconsistentRows(): array
    {
        return [
            'top-up of zero' => [['type' => 'top_up', 'amount_cents' => 0]],
            'top-up in a cycle' => [['type' => 'top_up', 'amount_cents' => 100, 'cycle_id' => 'cycle']],
            'charge that is positive' => [['type' => 'charge', 'amount_cents' => 100, 'cycle_id' => 'cycle']],
            'charge without a basket' => [['type' => 'charge', 'amount_cents' => -100]],
            'charge for a household and cycle' => [['type' => 'charge', 'amount_cents' => -100, 'cycle_id' => 'cycle']],
            'credit that is negative' => [['type' => 'credit', 'amount_cents' => -100, 'cycle_id' => 'cycle']],
            'credit without a basket' => [['type' => 'credit', 'amount_cents' => 100]],
            'credit for a household and cycle' => [['type' => 'credit', 'amount_cents' => 100, 'cycle_id' => 'cycle']],
            'correction of zero' => [['type' => 'correction', 'amount_cents' => 0, 'note' => 'x']],
            'correction without a note' => [['type' => 'correction', 'amount_cents' => 100, 'note' => '']],
            'unknown type' => [['type' => 'gift', 'amount_cents' => 100]],
        ];
    }

    /** @param array<string, mixed> $row */
    #[Test]
    #[DataProvider('inconsistentRows')]
    public function the_database_refuses_a_row_that_breaks_a_movement_invariant(array $row): void
    {
        if (($row['cycle_id'] ?? null) === 'cycle') {
            $row['cycle_id'] = (string) Str::uuid7();
        }

        $this->assertThrows(fn () => DB::table('wallet_movements')->insert([
            'id' => (string) Str::uuid7(),
            'household_id' => $this->household(),
            'recorded_by' => $this->account(),
            'occurred_at' => now(),
            'note' => null,
            'cycle_id' => null,
            ...$row,
        ]), QueryException::class);
    }

    #[Test]
    public function the_schema_has_no_household_and_cycle_charge_uniqueness_only_one_charge_per_basket(): void
    {
        $indexes = array_column(DB::select("select indexname from pg_indexes where tablename = 'wallet_movements'"), 'indexname');

        $this->assertNotContains('wallet_movements_one_charge_per_cycle', $indexes);
        $this->assertContains('wallet_movements_one_charge_per_basket', $indexes);
    }
}
