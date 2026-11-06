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
        DB::statement("create unique index stock_movements_one_sale_per_basket_product on stock_movements (basket_id, product_id) where type = 'sale' and basket_id is not null");
        DB::statement('create unique index stock_movements_one_correction_per_product on stock_movements (correction_id, product_id) where correction_id is not null');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
