<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basket_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('basket_id')->constrained('baskets');
            $table->string('kind')->default('stock');
            $table->foreignUuid('product_id')->constrained('products');
            $table->string('name');
            $table->string('unit');
            $table->integer('price_cents');
            $table->boolean('variable');
            $table->decimal('containers_per_unit', 10, 3)->nullable();
            $table->decimal('quantity', 10, 3);
            $table->timestampTz('created_at');
            $table->timestampTz('removed_at')->nullable();
            $table->index('product_id');
        });
        DB::statement('CREATE UNIQUE INDEX basket_lines_one_active_product ON basket_lines (basket_id, product_id) WHERE removed_at IS NULL');
        DB::statement('ALTER TABLE basket_lines ADD CONSTRAINT basket_lines_positive_quantity CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_lines');
    }
};
