<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baskets', function (Blueprint $table) {
            $table->foreignUuid('source_order_id')->nullable()->unique()->constrained('orders');
            $table->foreignUuid('reference_cycle_id')->nullable()->constrained('cycles');
            // The scheduler settles cycles without an actor, so a system-created weekly base has no creator.
            $table->uuid('created_by')->nullable()->change();
        });
        DB::statement("ALTER TABLE baskets ADD CONSTRAINT baskets_weekly_source CHECK ((kind = 'weekly' AND source_order_id IS NOT NULL AND reference_cycle_id IS NOT NULL) OR (kind = 'stock' AND source_order_id IS NULL))");

        Schema::table('basket_lines', function (Blueprint $table) {
            $table->foreignUuid('source_order_line_id')->nullable()->constrained('order_lines');
            $table->decimal('ordered_quantity', 10, 3)->nullable();
            $table->decimal('allocated_quantity', 10, 3)->nullable();
        });
        DB::statement('ALTER TABLE basket_lines DROP CONSTRAINT basket_lines_positive_quantity');
        DB::statement("ALTER TABLE basket_lines ADD CONSTRAINT basket_lines_quantity_bounds CHECK (quantity > 0 OR (kind = 'weekly' AND quantity = 0))");
        DB::statement('ALTER TABLE basket_lines DROP CONSTRAINT basket_lines_kind_reference');
        DB::statement(<<<'SQL'
            ALTER TABLE basket_lines ADD CONSTRAINT basket_lines_kind_reference CHECK (
                (kind = 'stock' AND product_id IS NOT NULL AND return_item_id IS NULL AND source_order_line_id IS NULL)
                OR (kind = 'return' AND product_id IS NULL AND return_item_id IS NOT NULL AND source_order_line_id IS NULL)
                OR (kind = 'weekly' AND product_id IS NOT NULL AND return_item_id IS NULL AND source_order_line_id IS NOT NULL
                    AND ordered_quantity IS NOT NULL AND allocated_quantity IS NOT NULL)
            )
        SQL);
        DB::statement('CREATE UNIQUE INDEX basket_lines_one_active_source_line ON basket_lines (basket_id, source_order_line_id) WHERE removed_at IS NULL AND source_order_line_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS basket_lines_one_active_source_line');
        DB::statement('ALTER TABLE basket_lines DROP CONSTRAINT basket_lines_kind_reference');
        DB::statement("ALTER TABLE basket_lines ADD CONSTRAINT basket_lines_kind_reference CHECK ((kind = 'stock' AND product_id IS NOT NULL AND return_item_id IS NULL) OR (kind = 'return' AND product_id IS NULL AND return_item_id IS NOT NULL))");
        DB::statement('ALTER TABLE basket_lines DROP CONSTRAINT basket_lines_quantity_bounds');
        DB::statement('ALTER TABLE basket_lines ADD CONSTRAINT basket_lines_positive_quantity CHECK (quantity > 0)');
        Schema::table('basket_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_order_line_id');
            $table->dropColumn(['ordered_quantity', 'allocated_quantity']);
        });
        DB::statement('ALTER TABLE baskets DROP CONSTRAINT baskets_weekly_source');
        Schema::table('baskets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reference_cycle_id');
            $table->dropConstrainedForeignId('source_order_id');
        });
    }
};
