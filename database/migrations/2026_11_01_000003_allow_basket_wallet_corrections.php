<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE wallet_movements DROP CONSTRAINT wallet_movements_shape');
        DB::statement(<<<'SQL'
            ALTER TABLE wallet_movements ADD CONSTRAINT wallet_movements_shape CHECK (
                (type = 'top_up' AND amount_cents > 0 AND cycle_id IS NULL AND basket_id IS NULL)
                OR (type = 'charge' AND amount_cents < 0 AND ((cycle_id IS NOT NULL AND basket_id IS NULL) OR (cycle_id IS NULL AND basket_id IS NOT NULL)))
                OR (type = 'credit' AND amount_cents > 0 AND ((cycle_id IS NOT NULL AND basket_id IS NULL) OR (cycle_id IS NULL AND basket_id IS NOT NULL)))
                OR (type = 'correction' AND amount_cents <> 0 AND note IS NOT NULL AND btrim(note) <> '' AND NOT (cycle_id IS NOT NULL AND basket_id IS NOT NULL))
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE wallet_movements DROP CONSTRAINT wallet_movements_shape');
        DB::statement(<<<'SQL'
            ALTER TABLE wallet_movements ADD CONSTRAINT wallet_movements_shape CHECK (
                (type = 'top_up' AND amount_cents > 0 AND cycle_id IS NULL AND basket_id IS NULL)
                OR (type = 'charge' AND amount_cents < 0 AND ((cycle_id IS NOT NULL AND basket_id IS NULL) OR (cycle_id IS NULL AND basket_id IS NOT NULL)))
                OR (type = 'credit' AND amount_cents > 0 AND ((cycle_id IS NOT NULL AND basket_id IS NULL) OR (cycle_id IS NULL AND basket_id IS NOT NULL)))
                OR (type = 'correction' AND amount_cents <> 0 AND note IS NOT NULL AND btrim(note) <> '' AND basket_id IS NULL)
            )
        SQL);
    }
};
