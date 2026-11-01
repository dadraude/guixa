<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_stock_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('stock_product_id')->constrained('products');
            $table->string('name');
            $table->string('unit');
            $table->integer('unit_price_cents');
            $table->boolean('variable');
            $table->decimal('containers_per_unit', 10, 3)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->timestampTz('removed_at', 6)->nullable();
        });

        Schema::create('order_return_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('return_item_id')->constrained('return_items');
            $table->string('name');
            $table->integer('amount_cents');
            $table->integer('quantity');
            $table->unique(['order_id', 'return_item_id']);
        });

        DB::statement('CREATE UNIQUE INDEX order_stock_lines_one_active_product ON order_stock_lines (order_id, stock_product_id) WHERE removed_at IS NULL');
        DB::statement('ALTER TABLE order_stock_lines ADD CONSTRAINT order_stock_lines_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_return_lines ADD CONSTRAINT order_return_lines_quantity_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_lines');
        Schema::dropIfExists('order_stock_lines');
    }
};
