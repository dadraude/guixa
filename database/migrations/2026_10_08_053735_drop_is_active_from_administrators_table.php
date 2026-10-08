<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Being an administrator is a yes/no the owner adds or removes (DRA-57);
     * whether the person is active belongs to their member profile.
     */
    public function up(): void
    {
        Schema::table('administrators', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('administrators', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });
    }
};
