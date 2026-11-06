<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basket_collection_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('basket_id')->constrained('baskets');
            $table->unsignedInteger('sequence');
            $table->string('type');
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->timestampTz('occurred_at');
            $table->text('reason')->nullable();
            $table->index(['basket_id', 'occurred_at']);
            $table->unique(['basket_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_collection_events');
    }
};
