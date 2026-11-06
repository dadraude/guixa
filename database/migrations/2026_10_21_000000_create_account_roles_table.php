<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('household_id')->nullable();
            $table->uuid('producer_id')->nullable()->unique();
        });

        Schema::create('account_roles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->primary(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['household_id', 'producer_id']);
        });
    }
};
