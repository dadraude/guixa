<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', fn (Blueprint $table) => $table->foreignUuid('basket_id')->nullable()->constrained('baskets'));
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_matches_shape');
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_matches_shape CHECK (
                (type = 'entry' AND quantity > 0 AND order_id IS NULL AND basket_id IS NULL)
                OR (type = 'adjustment' AND quantity <> 0 AND btrim(coalesce(note, '')) <> '' AND order_id IS NULL AND basket_id IS NULL)
                OR (type = 'sale' AND quantity < 0 AND ((order_id IS NOT NULL AND basket_id IS NULL) OR (order_id IS NULL AND basket_id IS NOT NULL)))
            )
        SQL);
        DB::statement("CREATE UNIQUE INDEX stock_movements_one_sale_per_basket_product ON stock_movements (basket_id, stock_product_id) WHERE type = 'sale' AND basket_id IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS stock_movements_one_sale_per_basket_product');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_matches_shape');
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('basket_id'));
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_matches_shape CHECK (
                (type = 'entry' AND quantity > 0 AND order_id IS NULL)
                OR (type = 'adjustment' AND quantity <> 0 AND btrim(coalesce(note, '')) <> '' AND order_id IS NULL)
                OR (type = 'sale' AND quantity < 0 AND order_id IS NOT NULL)
            )
        SQL);
    }
};
