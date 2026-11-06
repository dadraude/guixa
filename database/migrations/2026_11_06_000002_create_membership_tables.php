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
            $table->dateTime('deactivated_at', 6)->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->foreignUuid('household_id')->nullable()->constrained('households');
            $table->foreignUuid('producer_id')->nullable()->unique()->constrained('producers');
            $table->dateTime('activated_at', 6)->nullable();
            $table->dateTime('deactivated_at', 6)->nullable();
        });

        Schema::create('account_roles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->primary(['user_id', 'role']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->foreignUuid('account_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->dateTime('sent_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('account_roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('households');
    }
};
