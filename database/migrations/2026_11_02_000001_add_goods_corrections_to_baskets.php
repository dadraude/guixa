<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basket_adjustment_lines', function (Blueprint $table) {
            $table->foreignUuid('adjustment_id')->constrained('basket_adjustments');
            $table->foreignUuid('product_id')->constrained('products');
            $table->decimal('quantity_delta', 12, 3);
            $table->primary(['adjustment_id', 'product_id']);
        });
        DB::statement('ALTER TABLE basket_adjustment_lines ADD CONSTRAINT basket_adjustment_lines_nonzero CHECK (quantity_delta <> 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_adjustment_lines');
    }
};
