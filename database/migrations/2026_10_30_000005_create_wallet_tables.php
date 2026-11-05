<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('household_id')->constrained('households');
            $table->string('type');
            $table->integer('amount_cents');
            $table->timestampTz('occurred_at');
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->text('note')->nullable();
            // The cycle is a plain id: cycles belong to the Ordering context, so no foreign key.
            $table->uuid('cycle_id')->nullable();
            $table->foreignUuid('basket_id')->nullable()->constrained('baskets');
            $table->uuid('source_movement_id')->nullable();

            $table->index(['household_id', 'occurred_at']);
        });

        Schema::table('wallet_movements', function (Blueprint $table) {
            $table->foreign('source_movement_id')->references('id')->on('wallet_movements');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE wallet_movements ADD CONSTRAINT wallet_movements_shape CHECK (
                (type = 'top_up' AND amount_cents > 0 AND cycle_id IS NULL AND basket_id IS NULL)
                OR (type = 'charge' AND amount_cents < 0 AND cycle_id IS NULL AND basket_id IS NOT NULL)
                OR (type = 'credit' AND amount_cents > 0 AND cycle_id IS NULL AND basket_id IS NOT NULL)
                OR (type = 'correction' AND amount_cents <> 0 AND note IS NOT NULL AND btrim(note) <> '' AND NOT (cycle_id IS NOT NULL AND basket_id IS NOT NULL))
            )
        SQL);
        DB::statement("ALTER TABLE wallet_movements ADD CONSTRAINT wallet_correction_source_shape CHECK ((type = 'correction') OR source_movement_id IS NULL)");

        DB::statement("CREATE UNIQUE INDEX wallet_movements_one_charge_per_basket ON wallet_movements (basket_id) WHERE type IN ('charge', 'credit') AND basket_id IS NOT NULL");
        DB::statement('CREATE INDEX wallet_movements_source_id ON wallet_movements (source_movement_id) WHERE source_movement_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_movements');
    }
};
