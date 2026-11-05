<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('producer_id')->constrained('producers');
            $table->string('name');
            $table->string('unit');
            $table->integer('price_cents');
            $table->boolean('variable');
            $table->decimal('containers_per_unit', 10, 3)->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('stock')->default(false);
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_not_negative CHECK (price_cents >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_containers_per_unit_positive CHECK (containers_per_unit > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
