<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestampTz('created_at');
            $table->foreignUuid('validated_by')->nullable()->constrained('users');
            $table->timestampTz('validated_at')->nullable();
            $table->integer('validated_total_cents')->nullable();
            $table->foreignUuid('collected_by')->nullable()->constrained('users');
            $table->timestampTz('collected_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users');
            $table->timestampTz('cancelled_at')->nullable();
            $table->index(['household_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baskets');
    }
};
