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

            $table->index(['household_id', 'occurred_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE wallet_movements ADD CONSTRAINT wallet_movements_shape CHECK (
                (type = 'top_up' AND amount_cents > 0 AND cycle_id IS NULL)
                OR (type = 'charge' AND amount_cents < 0 AND cycle_id IS NOT NULL)
                OR (type = 'credit' AND amount_cents > 0 AND cycle_id IS NOT NULL)
                OR (type = 'correction' AND amount_cents <> 0 AND note IS NOT NULL AND btrim(note) <> '')
            )
        SQL);

        // Second line of defence for R23: a pickup charges or credits a household once per cycle.
        DB::statement(
            "CREATE UNIQUE INDEX wallet_movements_one_charge_per_cycle ON wallet_movements (household_id, cycle_id) WHERE type IN ('charge', 'credit')",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_movements');
    }
};
