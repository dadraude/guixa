<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A product without a row was received in full (R14); a row holds what a manager recorded.
        Schema::create('received_totals', function (Blueprint $table) {
            $table->foreignUuid('cycle_id')->constrained('cycles');
            $table->foreignUuid('producer_id')->constrained('producers');
            $table->foreignUuid('product_id')->constrained('products');
            $table->decimal('received_quantity', 10, 3);
            $table->primary(['cycle_id', 'product_id']);
        });

        DB::statement('ALTER TABLE received_totals ADD CONSTRAINT received_totals_received_not_negative CHECK (received_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('received_totals');
    }
};
