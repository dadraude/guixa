<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperative_settings', function (Blueprint $table) {
            $table->smallInteger('id')->primary();
            $table->smallInteger('pickup_weekday')->nullable();
        });
        // The row starts empty: there is no pickup weekday until a manager sets one (R4c).
        DB::table('cooperative_settings')->insert(['id' => 1, 'pickup_weekday' => null]);

        Schema::create('cycles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('pickup_date');
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->dateTime('settled_at', 6)->nullable();
            $table->dateTime('closed_at', 6)->nullable();
        });
        DB::statement('create unique index cycles_pickup_date_live_unique on cycles (pickup_date) where cancelled_at is null');

        Schema::create('cycle_producers', function (Blueprint $table) {
            $table->foreignUuid('cycle_id')->constrained('cycles');
            $table->foreignUuid('producer_id')->constrained('producers');
            $table->boolean('participating')->default(true);
            $table->dateTime('closes_at', 6)->nullable();
            $table->dateTime('sent_at', 6)->nullable();
            $table->dateTime('validated_at', 6)->nullable();
            $table->dateTime('distributed_at', 6)->nullable();
            $table->primary(['cycle_id', 'producer_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cycle_id')->constrained('cycles');
            $table->foreignUuid('household_id')->constrained('households');
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->unique(['cycle_id', 'household_id']);
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
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
            $table->dateTime('created_at', 6);
            $table->dateTime('removed_at', 6)->nullable();
            $table->foreignUuid('added_by')->constrained('users');
            $table->boolean('not_delivered')->default(false);
            $table->boolean('dropped')->default(false);
        });
        DB::statement('create unique index order_lines_one_active_product on order_lines (order_id, product_id) where removed_at is null');

        // A row means the producer marked the product unavailable in that cycle (R3c).
        Schema::create('product_unavailabilities', function (Blueprint $table) {
            $table->foreignUuid('cycle_id')->constrained('cycles');
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->primary(['cycle_id', 'product_id']);
        });

        // A product without a row was received in full (R14); a row holds what a manager recorded.
        Schema::create('received_totals', function (Blueprint $table) {
            $table->foreignUuid('cycle_id')->constrained('cycles');
            $table->foreignUuid('producer_id')->constrained('producers');
            $table->foreignUuid('product_id')->constrained('products');
            $table->decimal('received_quantity', 10, 3);
            $table->primary(['cycle_id', 'product_id']);
        });
    }

    public function down(): void
    {
        foreach (['received_totals', 'product_unavailabilities', 'order_lines', 'orders', 'cycle_producers', 'cycles', 'cooperative_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
