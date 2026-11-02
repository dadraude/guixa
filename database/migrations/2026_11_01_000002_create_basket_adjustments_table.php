<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basket_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('basket_id')->constrained('baskets');
            $table->integer('amount_cents');
            $table->text('reason');
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->timestampTz('occurred_at');
            $table->index(['basket_id', 'occurred_at', 'id']);
        });
        DB::statement("ALTER TABLE basket_adjustments ADD CONSTRAINT basket_adjustments_nonzero CHECK (amount_cents <> 0 AND btrim(reason) <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_adjustments');
    }
};
