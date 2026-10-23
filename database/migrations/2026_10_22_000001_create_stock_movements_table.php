<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // order_id has no foreign key yet (its table arrives with the Ordering tasks); cycle_id gets one in the cycles migration.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_product_id')->constrained('stock_products');
            $table->string('type');
            $table->decimal('quantity', 12, 3);
            $table->timestampTz('occurred_at');
            $table->uuid('recorded_by');
            $table->text('note')->nullable();
            $table->uuid('cycle_id')->nullable();
            $table->uuid('order_id')->nullable();

            $table->index(['stock_product_id', 'occurred_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_matches_shape CHECK (
                (type = 'entry' AND quantity > 0 AND order_id IS NULL)
                OR (type = 'adjustment' AND quantity <> 0 AND btrim(coalesce(note, '')) <> '' AND order_id IS NULL)
                OR (type = 'sale' AND quantity < 0 AND order_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
