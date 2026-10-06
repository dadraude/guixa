<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->uuid('household_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restoring NOT NULL would fail half-way on PostgreSQL; fail up front instead.
        if (DB::table('members')->whereNull('household_id')->exists()) {
            throw new RuntimeException('Cannot roll back: some members have no household. Assign one to each first.');
        }

        Schema::table('members', function (Blueprint $table) {
            $table->uuid('household_id')->nullable(false)->change();
        });
    }
};
