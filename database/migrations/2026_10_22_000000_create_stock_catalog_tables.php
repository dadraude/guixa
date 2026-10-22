<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('unit');
            $table->integer('price_cents');
            $table->boolean('variable');
            $table->decimal('containers_per_unit', 10, 3)->nullable();
            $table->boolean('active')->default(true);
        });

        DB::statement('ALTER TABLE stock_products ADD CONSTRAINT stock_products_price_not_negative CHECK (price_cents >= 0)');
        DB::statement('ALTER TABLE stock_products ADD CONSTRAINT stock_products_containers_per_unit_positive CHECK (containers_per_unit > 0)');

        Schema::create('return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->integer('amount_cents');
            $table->boolean('active')->default(true);
        });

        DB::statement('ALTER TABLE return_items ADD CONSTRAINT return_items_amount_positive CHECK (amount_cents > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('stock_products');
    }
};
