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
            $table->string('type');
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->timestampTz('occurred_at');
            $table->index(['basket_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_collection_events');
    }
};
