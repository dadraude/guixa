<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_movements', fn (Blueprint $table) => $table->foreignUuid('basket_id')->nullable()->constrained('baskets'));
        DB::statement('ALTER TABLE wallet_movements DROP CONSTRAINT wallet_movements_shape');
        DB::statement(<<<'SQL'
            ALTER TABLE wallet_movements ADD CONSTRAINT wallet_movements_shape CHECK (
                (type = 'top_up' AND amount_cents > 0 AND cycle_id IS NULL AND basket_id IS NULL)
                OR (type = 'charge' AND amount_cents < 0 AND ((cycle_id IS NOT NULL AND basket_id IS NULL) OR (cycle_id IS NULL AND basket_id IS NOT NULL)))
                OR (type = 'credit' AND amount_cents > 0 AND ((cycle_id IS NOT NULL AND basket_id IS NULL) OR (cycle_id IS NULL AND basket_id IS NOT NULL)))
                OR (type = 'correction' AND amount_cents <> 0 AND note IS NOT NULL AND btrim(note) <> '' AND basket_id IS NULL)
            )
        SQL);
        DB::statement("CREATE UNIQUE INDEX wallet_movements_one_charge_per_basket ON wallet_movements (basket_id) WHERE type IN ('charge', 'credit') AND basket_id IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS wallet_movements_one_charge_per_basket');
        DB::statement('ALTER TABLE wallet_movements DROP CONSTRAINT wallet_movements_shape');
        Schema::table('wallet_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('basket_id'));
        DB::statement(<<<'SQL'
            ALTER TABLE wallet_movements ADD CONSTRAINT wallet_movements_shape CHECK (
                (type = 'top_up' AND amount_cents > 0 AND cycle_id IS NULL)
                OR (type = 'charge' AND amount_cents < 0 AND cycle_id IS NOT NULL)
                OR (type = 'credit' AND amount_cents > 0 AND cycle_id IS NOT NULL)
                OR (type = 'correction' AND amount_cents <> 0 AND note IS NOT NULL AND btrim(note) <> '')
            )
        SQL);
    }
};
