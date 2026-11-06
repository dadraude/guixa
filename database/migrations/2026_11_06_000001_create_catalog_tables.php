<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->smallInteger('cutoff_days_before');
            $table->time('cutoff_time');
        });

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
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('producers');
    }
};
