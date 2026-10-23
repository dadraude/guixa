<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cycle_id')->constrained('cycles');
            $table->foreignUuid('household_id')->constrained('households');
            $table->string('pickup_status')->default('none');
            $table->timestampTz('pickup_done_at')->nullable();
            $table->integer('charged_cents')->nullable();
            $table->unique(['cycle_id', 'household_id']);
        });

        // The pickup columns wait for the tasks that use them (R19 onwards); the ordering part needs the rest.
        Schema::create('order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->foreignUuid('producer_id')->constrained('producers');
            $table->string('product_name');
            $table->string('unit');
            $table->integer('unit_price_cents');
            $table->boolean('variable');
            $table->decimal('containers_per_unit', 10, 3)->nullable();
            $table->decimal('ordered_quantity', 10, 3);
            $table->decimal('allocated_quantity', 10, 3);
            $table->boolean('allocated_by_hand')->default(false);
            $table->decimal('delivered_quantity', 10, 3)->nullable();
            $table->timestampTz('created_at', 6);
            $table->foreignUuid('added_by')->constrained('users');
            $table->boolean('not_delivered')->default(false);
            $table->boolean('dropped')->default(false);
            $table->unique(['order_id', 'product_id']);
        });

        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_ordered_not_negative CHECK (ordered_quantity >= 0)');
        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_allocated_not_negative CHECK (allocated_quantity >= 0)');
        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_delivered_not_negative CHECK (delivered_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
