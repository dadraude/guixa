<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basket_adjustment_lines', function (Blueprint $table) {
            $table->foreignUuid('adjustment_id')->constrained('basket_adjustments');
            $table->foreignUuid('product_id')->constrained('products');
            $table->decimal('quantity_delta', 12, 3);
            $table->primary(['adjustment_id', 'product_id']);
        });
        DB::statement('ALTER TABLE basket_adjustment_lines ADD CONSTRAINT basket_adjustment_lines_nonzero CHECK (quantity_delta <> 0)');

        Schema::table('stock_movements', fn (Blueprint $table) => $table->foreignUuid('correction_id')->nullable()->constrained('basket_adjustments'));
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_matches_shape');
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_matches_shape CHECK (
                (type = 'entry' AND quantity > 0 AND order_id IS NULL AND basket_id IS NULL AND correction_id IS NULL)
                OR (type = 'adjustment' AND quantity <> 0 AND btrim(coalesce(note, '')) <> '' AND order_id IS NULL
                    AND ((basket_id IS NULL AND correction_id IS NULL) OR (basket_id IS NOT NULL AND correction_id IS NOT NULL)))
                OR (type = 'sale' AND quantity < 0 AND correction_id IS NULL
                    AND ((order_id IS NOT NULL AND basket_id IS NULL) OR (order_id IS NULL AND basket_id IS NOT NULL)))
            )
        SQL);
        DB::statement('CREATE UNIQUE INDEX stock_movements_one_correction_per_product ON stock_movements (correction_id, stock_product_id) WHERE correction_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS stock_movements_one_correction_per_product');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_matches_shape');
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('correction_id'));
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_matches_shape CHECK (
                (type = 'entry' AND quantity > 0 AND order_id IS NULL AND basket_id IS NULL)
                OR (type = 'adjustment' AND quantity <> 0 AND btrim(coalesce(note, '')) <> '' AND order_id IS NULL AND basket_id IS NULL)
                OR (type = 'sale' AND quantity < 0 AND ((order_id IS NOT NULL AND basket_id IS NULL) OR (order_id IS NULL AND basket_id IS NOT NULL)))
            )
        SQL);
        Schema::dropIfExists('basket_adjustment_lines');
    }
};
