<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_movements', function (Blueprint $table) {
            $table->uuid('id');
            // Declared before the self-referencing foreign key so PostgreSQL finds the key it points at.
            $table->primary('id');
            $table->foreignUuid('household_id')->constrained('households');
            $table->string('type');
            $table->integer('amount_cents');
            $table->dateTime('occurred_at', 6);
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->text('note')->nullable();
            // The cycle is a plain id: cycles belong to the Ordering context, so no foreign key.
            $table->uuid('cycle_id')->nullable();
            $table->foreignUuid('basket_id')->nullable()->constrained('baskets');
            $table->foreignUuid('source_movement_id')->nullable()->constrained('wallet_movements');
            $table->index(['household_id', 'occurred_at']);
        });
        DB::statement("create unique index wallet_movements_one_charge_per_basket on wallet_movements (basket_id) where type in ('charge', 'credit') and basket_id is not null");
        DB::statement('create index wallet_movements_source_id on wallet_movements (source_movement_id) where source_movement_id is not null');
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_movements');
    }
};
