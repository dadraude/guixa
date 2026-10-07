<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations were replaced by creating members by hand (ADR 0008). The app
 * is not in production, so there is nothing to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('invitations');
    }

    public function down(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('household_id')->constrained();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('email');
        });
    }
};
