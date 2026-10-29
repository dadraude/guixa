<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->integer('amount_cents');
            $table->boolean('active')->default(true);
        });

        DB::statement('ALTER TABLE return_items ADD CONSTRAINT return_items_amount_positive CHECK (amount_cents > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
