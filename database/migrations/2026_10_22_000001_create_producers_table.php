<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->smallInteger('cutoff_days_before');
            $table->time('cutoff_time');
        });

        DB::statement('ALTER TABLE producers ADD CONSTRAINT producers_cutoff_days_before_range CHECK (cutoff_days_before BETWEEN 1 AND 6)');

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('producer_id')->references('id')->on('producers');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['producer_id']);
        });

        Schema::dropIfExists('producers');
    }
};
