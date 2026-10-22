<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->foreign('household_id')->references('id')->on('households');
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->foreignUuid('account_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->timestampTz('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['household_id']);
            $table->dropColumn(['activated_at', 'deactivated_at']);
        });

        Schema::dropIfExists('households');
    }
};
