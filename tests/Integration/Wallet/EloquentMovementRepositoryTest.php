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

    /** @return array<string, array{array<string, mixed>}> */
    public static function inconsistentRows(): array
    {
        return [
            'top-up of zero' => [['type' => 'top_up', 'amount_cents' => 0]],
            'top-up in a cycle' => [['type' => 'top_up', 'amount_cents' => 100, 'cycle_id' => 'cycle']],
            'charge that is positive' => [['type' => 'charge', 'amount_cents' => 100, 'cycle_id' => 'cycle']],
            'charge without a cycle' => [['type' => 'charge', 'amount_cents' => -100]],
            'credit that is negative' => [['type' => 'credit', 'amount_cents' => -100, 'cycle_id' => 'cycle']],
            'credit without a cycle' => [['type' => 'credit', 'amount_cents' => 100]],
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
    public function the_partial_unique_index_rejects_a_second_charge_or_credit_for_a_household_and_cycle(): void
    {
        $household = $this->household();
        $cycle = (string) Str::uuid7();
        $insert = fn (string $type, int $cents) => fn () => DB::table('wallet_movements')->insert([
            'id' => (string) Str::uuid7(), 'household_id' => $household, 'type' => $type, 'amount_cents' => $cents,
            'occurred_at' => now(), 'recorded_by' => $this->account(), 'cycle_id' => $cycle,
        ]);
        $insert('charge', -100)();

        $this->assertThrows($insert('credit', 50), QueryException::class);
    }
}
