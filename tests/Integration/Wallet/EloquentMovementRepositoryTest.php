<?php

namespace Tests\Integration\Wallet;

use App\Models\User;
use Guixa\Wallet\Domain\MovementRepository;
use Guixa\Wallet\Infrastructure\Persistence\EloquentMovementRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

    #[Test]
    public function the_schema_has_no_household_and_cycle_charge_uniqueness_only_one_charge_per_basket(): void
    {
        $indexes = array_column(Schema::getIndexes('wallet_movements'), 'name');

        $this->assertNotContains('wallet_movements_one_charge_per_cycle', $indexes);
        $this->assertContains('wallet_movements_one_charge_per_basket', $indexes);
    }
}
