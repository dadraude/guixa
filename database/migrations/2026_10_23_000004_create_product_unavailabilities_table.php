<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A row means the producer marked the product unavailable in that cycle (R3c).
        Schema::create('product_unavailabilities', function (Blueprint $table) {
            $table->foreignUuid('cycle_id')->constrained('cycles');
            $table->foreignUuid('product_id')->constrained('products');
            $table->primary(['cycle_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_unavailabilities');
    }
};
