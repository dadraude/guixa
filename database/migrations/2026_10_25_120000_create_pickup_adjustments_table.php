<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_pickup_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->string('name');
            $table->string('unit');
            $table->integer('unit_price_cents');
            $table->bigInteger('quantity_delta_thousandths');
            $table->integer('amount_cents');
            $table->decimal('target_quantity', 10, 3);
            $table->timestampTz('recorded_at', 6);
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->index(['order_id', 'recorded_at', 'id']);
        });
        DB::statement('ALTER TABLE order_pickup_adjustments ADD CONSTRAINT pickup_adjustment_target_nonnegative CHECK (target_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_pickup_adjustments');
    }
};
