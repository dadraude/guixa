<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('basket_lines', function (Blueprint $table) {
            $table->uuid('product_id')->nullable()->change();
            $table->foreignUuid('return_item_id')->nullable()->constrained('return_items');
        });
        DB::statement("ALTER TABLE basket_lines ADD CONSTRAINT basket_lines_kind_reference CHECK ((kind = 'stock' AND product_id IS NOT NULL AND return_item_id IS NULL) OR (kind = 'return' AND product_id IS NULL AND return_item_id IS NOT NULL))");
        DB::statement('CREATE UNIQUE INDEX basket_lines_one_active_return ON basket_lines (basket_id, return_item_id) WHERE removed_at IS NULL AND return_item_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS basket_lines_one_active_return');
        DB::statement('ALTER TABLE basket_lines DROP CONSTRAINT basket_lines_kind_reference');
        Schema::table('basket_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_item_id');
            $table->uuid('product_id')->nullable(false)->change();
        });
    }
};
