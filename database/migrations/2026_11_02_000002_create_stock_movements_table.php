<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products');
            $table->string('type');
            $table->decimal('quantity', 12, 3);
            $table->timestampTz('occurred_at');
            $table->uuid('recorded_by');
            $table->text('note')->nullable();
            $table->foreignUuid('cycle_id')->nullable()->constrained('cycles');
            $table->foreignUuid('basket_id')->nullable()->constrained('baskets');
            $table->foreignUuid('correction_id')->nullable()->constrained('basket_adjustments');

            $table->index(['product_id', 'occurred_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_matches_shape CHECK (
                (type = 'entry' AND quantity > 0 AND basket_id IS NULL AND correction_id IS NULL)
                OR (type = 'adjustment' AND quantity <> 0 AND btrim(coalesce(note, '')) <> ''
                    AND ((basket_id IS NULL AND correction_id IS NULL) OR (basket_id IS NOT NULL AND correction_id IS NOT NULL)))
                OR (type = 'sale' AND quantity < 0 AND basket_id IS NOT NULL AND correction_id IS NULL)
            )
        SQL);
        DB::statement("CREATE UNIQUE INDEX stock_movements_one_sale_per_basket_product ON stock_movements (basket_id, product_id) WHERE type = 'sale' AND basket_id IS NOT NULL");
        DB::statement('CREATE UNIQUE INDEX stock_movements_one_correction_per_product ON stock_movements (correction_id, product_id) WHERE correction_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
