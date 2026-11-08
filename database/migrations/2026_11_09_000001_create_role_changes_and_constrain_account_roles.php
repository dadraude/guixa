<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("alter table account_roles add constraint account_roles_role_check check (role in ('manager', 'finance', 'producer', 'catalogue', 'receiver'))");

        Schema::create('role_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('change');
            $table->foreignUuid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('occurred_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_changes');
        DB::statement('alter table account_roles drop constraint account_roles_role_check');
    }
};
