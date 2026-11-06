<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baskets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('household_id')->constrained('households');
            $table->string('household_name');
            $table->string('kind')->default('stock');
            $table->string('state');
            // The scheduler settles cycles without an actor, so a system-created weekly base has no creator.
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->dateTime('created_at', 6);
            $table->foreignUuid('validated_by')->nullable()->constrained('users');
            $table->dateTime('validated_at', 6)->nullable();
            $table->integer('validated_total_cents')->nullable();
            $table->foreignUuid('collected_by')->nullable()->constrained('users');
            $table->dateTime('collected_at', 6)->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users');
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->foreignUuid('source_order_id')->nullable()->constrained('orders');
            $table->boolean('late')->default(false);
            $table->index(['household_id', 'created_at']);
        });
        DB::statement('create unique index baskets_one_base_per_source_order on baskets (source_order_id) where source_order_id is not null and not late');
        DB::statement("create unique index baskets_one_open_late_per_source_order on baskets (source_order_id) where late and state in ('draft', 'nothing_to_collect')");

        Schema::create('basket_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('basket_id')->constrained('baskets');
            $table->string('kind')->default('stock');
            $table->foreignUuid('product_id')->nullable()->constrained('products');
            $table->foreignUuid('return_item_id')->nullable()->constrained('return_items');
            $table->foreignUuid('source_order_line_id')->nullable()->constrained('order_lines');
            $table->string('name');
            $table->string('unit');
            $table->integer('price_cents');
            $table->boolean('variable');
            $table->decimal('containers_per_unit', 10, 3)->nullable();
            $table->decimal('quantity', 10, 3);
            $table->decimal('ordered_quantity', 10, 3)->nullable();
            $table->decimal('allocated_quantity', 10, 3)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('removed_at', 6)->nullable();
            $table->index('product_id');
        });
        DB::statement('create unique index basket_lines_one_active_product on basket_lines (basket_id, product_id) where removed_at is null');
        DB::statement('create unique index basket_lines_one_active_return on basket_lines (basket_id, return_item_id) where removed_at is null and return_item_id is not null');
        DB::statement('create unique index basket_lines_one_active_source_line on basket_lines (basket_id, source_order_line_id) where removed_at is null and source_order_line_id is not null');

        Schema::create('basket_collection_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('basket_id')->constrained('baskets');
            $table->unsignedInteger('sequence');
            $table->string('type');
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->dateTime('occurred_at', 6);
            $table->text('reason')->nullable();
            $table->index(['basket_id', 'occurred_at']);
            $table->unique(['basket_id', 'sequence']);
        });

        Schema::create('basket_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('basket_id')->constrained('baskets');
            $table->integer('amount_cents');
            $table->text('reason');
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->dateTime('occurred_at', 6);
            $table->index(['basket_id', 'occurred_at', 'id']);
        });

        Schema::create('basket_adjustment_lines', function (Blueprint $table) {
            $table->foreignUuid('adjustment_id')->constrained('basket_adjustments');
            $table->foreignUuid('product_id')->constrained('products');
            $table->decimal('quantity_delta', 12, 3);
            $table->primary(['adjustment_id', 'product_id']);
        });
    }

    public function down(): void
    {
        foreach (['basket_adjustment_lines', 'basket_adjustments', 'basket_collection_events', 'basket_lines', 'baskets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
